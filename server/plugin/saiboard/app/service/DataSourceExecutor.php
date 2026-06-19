<?php

namespace plugin\saiboard\app\service;

use InvalidArgumentException;
use PDO;
use plugin\saiboard\app\model\Datasource;
use plugin\saiboard\app\model\QueryTemplate;
use RuntimeException;
use support\Redis;
use support\think\Cache;
use Throwable;

class DataSourceExecutor
{
    private const CACHE_LOCK_WAIT_USEC = 100000;
    private const CACHE_LOCK_TIMEOUT_SECONDS = 3.0;
    private const CACHE_STALE_MIN_TTL = 60;
    private const CACHE_STALE_MAX_TTL = 86400;

    public function __construct(private readonly RuntimeMetrics $metrics = new RuntimeMetrics())
    {
    }

    public function testDatasource(Datasource $datasource, array $templateConfig = []): array
    {
        return match ((string) $datasource->type) {
            'mysql' => $this->testMysql($datasource->config),
            'http' => $this->executeHttp(
                $datasource->config,
                ['dataset_type' => 'http_passthrough', 'config' => $templateConfig],
                true
            ),
            default => throw new InvalidArgumentException('数据源类型不支持'),
        };
    }

    public function preview(QueryTemplate $template, array $runtimeParams = []): array
    {
        return $this->execute($template, true, $runtimeParams);
    }

    public function schema(Datasource $datasource, string $table = ''): array
    {
        if ((string) $datasource->type !== 'mysql') {
            throw new InvalidArgumentException('只有 MySQL 数据源支持读取表结构');
        }

        $pdo = $this->pdo($datasource->config);
        $tables = array_values(array_map('strval', $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: []));
        $result = [
            'tables' => array_map(static fn (string $name) => ['name' => $name], $tables),
            'columns' => [],
        ];

        $table = trim($table);
        if ($table === '') {
            return $result;
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !in_array($table, $tables, true)) {
            throw new InvalidArgumentException('数据表不存在或不允许访问');
        }

        $rows = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result['columns'] = array_map(fn (array $row) => [
            'name' => (string) $row['Field'],
            'type' => (string) $row['Type'],
            'kind' => $this->columnKind((string) $row['Type']),
        ], $rows);

        return $result;
    }

    public function execute(
        QueryTemplate $template,
        bool $forceRefresh = false,
        array $runtimeParams = [],
        array $metricContext = []
    ): array {
        $datasource = Datasource::where('id', (int) $template->datasource_id)
            ->where('status', 1)
            ->findOrEmpty();
        if ($datasource->isEmpty()) {
            throw new InvalidArgumentException('数据源不存在或已停用');
        }

        $cacheTtl = max(0, (int) $datasource->cache_ttl);
        $cacheKey = $this->cacheKey($template, $datasource, $runtimeParams);
        $recordMetrics = !$forceRefresh;
        $metricTags = array_merge($metricContext, [
            'template' => (string) $template->id,
            'datasource' => (string) $datasource->id,
            'type' => (string) $datasource->type,
        ]);
        if (!$forceRefresh && $cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                $this->metrics->record('cache_hit', $metricTags);
                return $cached;
            }
            $this->metrics->record('cache_miss', $metricTags);
        }

        if (!$forceRefresh && $cacheTtl > 0) {
            try {
                return $this->executeWithCacheLock(
                    $cacheKey,
                    $cacheTtl,
                    fn () => $this->executeSource($datasource, $template, $runtimeParams, $recordMetrics, $metricTags),
                    $metricTags
                );
            } catch (Throwable $exception) {
                $stale = Cache::get($this->staleCacheKey($cacheKey));
                if (is_array($stale)) {
                    $this->metrics->record('stale_hit', $metricTags);
                    return $stale;
                }
                throw $exception;
            }
        }

        $result = $this->executeSource($datasource, $template, $runtimeParams, $recordMetrics, $metricTags);
        if ($cacheTtl > 0) {
            $this->storeCacheResult($cacheKey, $result, $cacheTtl, $metricTags);
        }

        return $result;
    }

    private function executeSource(
        Datasource $datasource,
        QueryTemplate $template,
        array $runtimeParams,
        bool $recordMetrics = true,
        array $metricTags = []
    ): array {
        try {
            $result = match ((string) $datasource->type) {
                'mysql' => $this->executeMysql(
                    $datasource->config,
                    (string) $template->dataset_type,
                    $template->config,
                    $runtimeParams
                ),
                'http' => $this->executeHttp($datasource->config, [
                    'dataset_type' => (string) $template->dataset_type,
                    'config' => $template->config,
                ], false, $runtimeParams),
                default => throw new InvalidArgumentException('数据源类型不支持'),
            };

            if ((string) $datasource->last_error !== '') {
                $datasource->save(['last_error' => null]);
            }

            if ($recordMetrics) {
                $this->metrics->record('source_success', $metricTags);
            }

            return $result;
        } catch (Throwable $exception) {
            if ($recordMetrics) {
                $this->metrics->record('source_fail', $metricTags);
            }
            $datasource->save(['last_error' => $this->safeError($exception->getMessage())]);
            throw $exception;
        }
    }

    private function executeWithCacheLock(string $cacheKey, int $cacheTtl, callable $callback, array $metricTags = []): array
    {
        $redisLock = $this->acquireRedisLock($cacheKey);
        if ($redisLock['available']) {
            if ($redisLock['acquired']) {
                $this->metrics->record('redis_lock_acquired', $metricTags);
                try {
                    $cached = Cache::get($cacheKey);
                    if (is_array($cached)) {
                        $this->metrics->record('cache_hit', $metricTags);
                        return $cached;
                    }

                    $result = $callback();
                    $this->storeCacheResult($cacheKey, $result, $cacheTtl, $metricTags);
                    return $result;
                } finally {
                    $this->releaseRedisLock($redisLock['key'], $redisLock['token']);
                }
            }

            $this->metrics->record('lock_wait', $metricTags);
            $cached = $this->waitForCachedResult($cacheKey);
            if (is_array($cached)) {
                $this->metrics->record('cache_hit', $metricTags);
                return $cached;
            }

            $this->metrics->record('lock_timeout', $metricTags);
            throw new RuntimeException('数据缓存刷新中');
        }

        $lock = $this->openCacheLock($cacheKey);
        if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
            $this->metrics->record('file_lock_acquired', $metricTags);
            try {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    $this->metrics->record('cache_hit', $metricTags);
                    return $cached;
                }

                $result = $callback();
                $this->storeCacheResult($cacheKey, $result, $cacheTtl, $metricTags);
                return $result;
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        $this->metrics->record('lock_wait', $metricTags);
        $cached = $this->waitForCachedResult($cacheKey);
        if (is_array($cached)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            $this->metrics->record('cache_hit', $metricTags);
            return $cached;
        }

        if ($lock && flock($lock, LOCK_EX)) {
            $this->metrics->record('file_lock_acquired', $metricTags);
            try {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    $this->metrics->record('cache_hit', $metricTags);
                    return $cached;
                }

                $result = $callback();
                $this->storeCacheResult($cacheKey, $result, $cacheTtl, $metricTags);
                return $result;
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        $this->metrics->record('lock_timeout', $metricTags);
        $result = $callback();
        $this->storeCacheResult($cacheKey, $result, $cacheTtl, $metricTags);
        return $result;
    }

    private function waitForCachedResult(string $cacheKey): ?array
    {
        $deadline = microtime(true) + self::CACHE_LOCK_TIMEOUT_SECONDS;
        while (microtime(true) < $deadline) {
            usleep(self::CACHE_LOCK_WAIT_USEC);
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        return null;
    }

    private function acquireRedisLock(string $cacheKey): array
    {
        if (!(bool) config('plugin.saiboard.app.runtime.lock.redis', true)) {
            return ['available' => false, 'acquired' => false, 'key' => '', 'token' => ''];
        }

        $key = 'saiboard:runtime:lock:' . md5($cacheKey);
        $token = bin2hex(random_bytes(16));
        $ttl = max(1, (int) config('plugin.saiboard.app.runtime.lock.ttl', 15));

        try {
            return [
                'available' => true,
                'acquired' => (bool) Redis::set($key, $token, 'EX', $ttl, 'NX'),
                'key' => $key,
                'token' => $token,
            ];
        } catch (Throwable) {
            return ['available' => false, 'acquired' => false, 'key' => '', 'token' => ''];
        }
    }

    private function releaseRedisLock(string $key, string $token): void
    {
        if ($key === '' || $token === '') {
            return;
        }

        try {
            $script = <<<'LUA'
if redis.call('GET', KEYS[1]) == ARGV[1] then
    return redis.call('DEL', KEYS[1])
end
return 0
LUA;
            Redis::eval($script, 1, $key, $token);
        } catch (Throwable) {
            // 锁带 TTL，释放失败时让 Redis 自动过期。
        }
    }

    private function openCacheLock(string $cacheKey): mixed
    {
        $dir = runtime_path('saiboard-locks');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return @fopen($dir . DIRECTORY_SEPARATOR . md5($cacheKey) . '.lock', 'c');
    }

    private function storeCacheResult(string $cacheKey, array $result, int $cacheTtl, array $metricTags = []): void
    {
        Cache::set($cacheKey, $result, $cacheTtl);
        Cache::set($this->staleCacheKey($cacheKey), $result, $this->staleCacheTtl($cacheTtl));
        $this->metrics->record('cache_store', $metricTags);
    }

    private function staleCacheKey(string $cacheKey): string
    {
        return $cacheKey . ':stale';
    }

    private function staleCacheTtl(int $cacheTtl): int
    {
        return min(self::CACHE_STALE_MAX_TTL, max(self::CACHE_STALE_MIN_TTL, $cacheTtl * 6));
    }

    private function testMysql(array $config): array
    {
        $pdo = $this->pdo($config);
        $row = $pdo->query('SELECT 1 AS ok')->fetch(PDO::FETCH_ASSOC);
        return [
            'rows' => [$row],
            'total' => 1,
            'diagnostics' => [
                'type' => 'mysql',
                'host' => (string) ($config['host'] ?? ''),
                'port' => (int) ($config['port'] ?? 3306),
                'database' => (string) ($config['database'] ?? ''),
                'message' => '连接成功',
            ],
        ];
    }

    private function executeMysql(
        array $datasourceConfig,
        string $datasetType,
        array $templateConfig,
        array $runtimeParams = []
    ): array {
        $pdo = $this->pdo($datasourceConfig);
        [$sql, $bindings, $mode] = (new SqlBuilder())->build(
            $pdo,
            $datasetType,
            $templateConfig,
            $runtimeParams
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($mode === 'count') {
            $total = (int) ($rows[0]['total'] ?? 0);
            return ['rows' => [['total' => $total]], 'total' => $total];
        }

        return ['rows' => $rows, 'total' => count($rows)];
    }

    private function pdo(array $config): PDO
    {
        foreach (['host', 'database', 'username'] as $key) {
            if (trim((string) ($config[$key] ?? '')) === '') {
                throw new InvalidArgumentException('MySQL 数据源配置不完整');
            }
        }

        $host = (string) $config['host'];
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) $config['database'];
        $charset = preg_match('/^[A-Za-z0-9_]+$/', (string) ($config['charset'] ?? 'utf8mb4'))
            ? (string) ($config['charset'] ?? 'utf8mb4')
            : 'utf8mb4';

        return new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
            (string) $config['username'],
            (string) ($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    private function executeHttp(
        array $datasourceConfig,
        array $template,
        bool $withDiagnostics = false,
        array $runtimeParams = []
    ): array
    {
        $templateConfig = is_array($template['config'] ?? null) ? $template['config'] : [];
        $request = $this->buildHttpRequest($datasourceConfig, $templateConfig, $runtimeParams);
        $this->assertPublicHttpUrl($request['url']);
        $headers = $this->normalizeHeaders($datasourceConfig['headers'] ?? []);
        [$payload, $diagnostics] = $this->requestJson(
            $request['url'],
            $headers,
            $request['method'],
            $request['body']
        );

        $result = $this->normalizeHttpPayload($payload, $templateConfig);
        return $withDiagnostics ? $result + ['diagnostics' => $diagnostics] : $result;
    }

    private function buildHttpRequest(array $datasourceConfig, array $templateConfig, array $runtimeParams = []): array
    {
        $baseUrl = trim((string) ($datasourceConfig['url'] ?? ''));
        if ($baseUrl === '') {
            throw new InvalidArgumentException('HTTP 数据源 URL 必须填写');
        }

        $path = trim((string) $this->replaceHttpRuntimeParams(
            (string) ($templateConfig['path'] ?? ''),
            $runtimeParams,
            true
        ));
        $url = $path === '' ? $baseUrl : rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        $params = $this->replaceHttpRuntimeParams(array_merge(
            $this->normalizeObject($datasourceConfig['params'] ?? [], 'HTTP 数据源默认参数'),
            $this->normalizeObject($templateConfig['params'] ?? [], 'HTTP 查询模板请求参数')
        ), $runtimeParams);
        if ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }

        $method = $this->normalizeHttpMethod($templateConfig['method'] ?? $datasourceConfig['method'] ?? 'GET');
        $body = $method === 'POST'
            ? $this->replaceHttpRuntimeParams(
                $this->normalizeObject($templateConfig['body'] ?? [], 'HTTP 查询模板 JSON Body'),
                $runtimeParams
            )
            : [];

        return [
            'url' => $url,
            'method' => $method,
            'body' => $body,
        ];
    }

    private function normalizeHttpMethod(mixed $method): string
    {
        $method = strtoupper(trim((string) $method));
        if ($method === '') {
            return 'GET';
        }
        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new InvalidArgumentException('HTTP 查询模板请求方法只支持 GET 或 POST');
        }

        return $method;
    }

    private function replaceHttpRuntimeParams(mixed $value, array $runtimeParams, bool $urlEncode = false): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replaceHttpRuntimeParams($item, $runtimeParams, $urlEncode);
            }

            return $value;
        }
        if (!is_string($value) || !str_contains($value, ':')) {
            return $value;
        }

        if (preg_match('/^:([A-Za-z][A-Za-z0-9_]*)$/', $value, $matches)) {
            $name = $matches[1];
            if (!array_key_exists($name, $runtimeParams)) {
                return $value;
            }

            $runtimeValue = $this->normalizeHttpRuntimeValue($runtimeParams[$name]);
            if ($urlEncode) {
                if (!is_scalar($runtimeValue)) {
                    throw new InvalidArgumentException("HTTP 查询模板路径参数 {$name} 必须是标量");
                }

                return rawurlencode((string) $runtimeValue);
            }

            return $runtimeValue;
        }

        return preg_replace_callback('/\:([A-Za-z][A-Za-z0-9_]*)/', function (array $matches) use ($runtimeParams, $urlEncode): string {
            $name = $matches[1];
            if (!array_key_exists($name, $runtimeParams)) {
                return $matches[0];
            }

            $runtimeValue = $this->normalizeHttpRuntimeValue($runtimeParams[$name]);
            if (!is_scalar($runtimeValue)) {
                if ($urlEncode) {
                    throw new InvalidArgumentException("HTTP 查询模板路径参数 {$name} 必须是标量");
                }

                return $matches[0];
            }

            $replacement = (string) $runtimeValue;
            return $urlEncode ? rawurlencode($replacement) : $replacement;
        }, $value) ?? $value;
    }

    private function normalizeHttpRuntimeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            if (count($value) > 100) {
                throw new InvalidArgumentException('HTTP 查询参数最多支持 100 个值');
            }

            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizeHttpRuntimeValue($item);
            }

            return $value;
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('HTTP 查询参数值不正确');
        }

        return mb_substr(trim((string) $value), 0, 500);
    }

    private function assertPublicHttpUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('HTTP 数据源 URL 不正确，只允许 http/https');
        }
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            throw new InvalidArgumentException("HTTP 数据源不允许访问本机地址：{$host}");
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
            foreach ($records as $record) {
                if (!empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }
        if ($ips === []) {
            throw new InvalidArgumentException("HTTP 数据源域名无法解析：{$host}");
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException("HTTP 数据源不允许访问内网或保留地址：{$host}");
            }
        }
    }

    private function normalizeHeaders(mixed $headers): array
    {
        $headers = $this->normalizeObject($headers, 'HTTP 请求头');

        $result = [];
        foreach ($headers as $name => $value) {
            $name = trim((string) $name);
            if ($name === '' || preg_match('/[\r\n:]/', $name)) {
                continue;
            }
            $result[] = $name . ': ' . str_replace(["\r", "\n"], '', (string) $value);
        }

        return $result;
    }

    private function requestJson(string $url, array $headers, string $method = 'GET', array $body = []): array
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        $diagnostics = [
            'type' => 'http',
            'host' => $host,
            'method' => $method,
            'status' => 0,
            'message' => '请求成功',
        ];
        $jsonBody = null;
        if ($method === 'POST') {
            $jsonBody = json_encode($body === [] ? (object) [] : $body, JSON_UNESCAPED_UNICODE);
            if ($jsonBody === false) {
                throw new InvalidArgumentException('HTTP 查询模板 JSON Body 编码失败');
            }
            $headers = $this->ensureHeader($headers, 'Content-Type', 'application/json');
        }

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ];
            if ($jsonBody !== null) {
                $options[CURLOPT_POSTFIELDS] = $jsonBody;
            }
            curl_setopt_array($curl, $options);
            $body = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            $diagnostics['status'] = $status;
            if ($body === false) {
                throw new InvalidArgumentException('HTTP 数据源请求失败：' . ($error ?: '网络错误'));
            }
            if ($status >= 400) {
                throw new InvalidArgumentException("HTTP 数据源请求失败，状态码 {$status}");
            }
            if ($status === 0) {
                throw new InvalidArgumentException('HTTP 数据源请求失败，未收到有效响应');
            }
        } else {
            $httpResponseHeader = [];
            $contextOptions = [
                'method' => $method,
                'timeout' => 10,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
            ];
            if ($jsonBody !== null) {
                $contextOptions['content'] = $jsonBody;
            }
            $body = file_get_contents($url, false, stream_context_create([
                'http' => $contextOptions,
            ]));
            $httpResponseHeader = $http_response_header ?? [];
            $status = $this->statusCodeFromHeaders($httpResponseHeader);
            $diagnostics['status'] = $status;
            if ($body === false) {
                throw new InvalidArgumentException('HTTP 数据源请求失败');
            }
            if ($status >= 400) {
                throw new InvalidArgumentException("HTTP 数据源请求失败，状态码 {$status}");
            }
        }

        $payload = json_decode((string) $body, true);
        if (!is_array($payload)) {
            $status = (int) ($diagnostics['status'] ?? 0);
            $suffix = $status > 0 ? "，状态码 {$status}" : '';
            throw new InvalidArgumentException("HTTP 数据源返回内容不是 JSON{$suffix}");
        }

        return [$payload, $diagnostics];
    }

    private function normalizeHttpPayload(array $payload, array $templateConfig): array
    {
        $path = trim((string) ($templateConfig['response_path'] ?? ''));
        $source = $path === '' ? $payload : $this->extractHttpPath($payload, $path, '响应数据路径');

        if ($path === '' && isset($payload['rows']) && is_array($payload['rows'])) {
            $rows = $this->normalizeHttpRows($payload['rows']);
            return [
                'rows' => $rows,
                'total' => $this->httpTotal($payload, $payload, $templateConfig) ?? count($rows),
            ];
        }

        if (is_array($source) && isset($source['rows']) && is_array($source['rows'])) {
            $rows = $this->normalizeHttpRows($source['rows']);
            return [
                'rows' => $rows,
                'total' => $this->httpTotal($payload, $source, $templateConfig) ?? count($rows),
            ];
        }

        if ($path === '' && array_key_exists('data', $payload)) {
            $source = $payload['data'];
        }

        $rows = $this->normalizeHttpRows($source);
        return [
            'rows' => $rows,
            'total' => $this->httpTotal($payload, $source, $templateConfig) ?? count($rows),
        ];
    }

    private function normalizeHttpRows(mixed $source): array
    {
        if (is_array($source) && array_is_list($source)) {
            return array_map(
                static fn (mixed $row) => is_array($row) ? $row : ['value' => $row],
                $source
            );
        }

        if (is_array($source)) {
            return [$source];
        }

        return [['value' => $source]];
    }

    private function httpTotal(array $payload, mixed $source, array $templateConfig): ?int
    {
        $path = trim((string) ($templateConfig['total_path'] ?? ''));
        if ($path !== '') {
            $value = $this->extractHttpPath($payload, $path, '总数路径');
            return is_numeric($value) ? (int) $value : null;
        }

        if (is_array($source) && isset($source['total']) && is_numeric($source['total'])) {
            return (int) $source['total'];
        }
        if (isset($payload['total']) && is_numeric($payload['total'])) {
            return (int) $payload['total'];
        }

        return null;
    }

    private function extractHttpPath(array $payload, string $path, string $label): mixed
    {
        if (!preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*$/', $path)) {
            throw new InvalidArgumentException("HTTP 查询模板{$label}格式不正确");
        }

        $current = $payload;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                throw new InvalidArgumentException("HTTP 查询模板{$label}不存在：{$path}");
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private function normalizeObject(mixed $value, string $label): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException("{$label}必须是 JSON 对象");
        }

        return $value;
    }

    private function ensureHeader(array $headers, string $name, string $value): array
    {
        foreach ($headers as $header) {
            if (str_starts_with(strtolower((string) $header), strtolower($name) . ':')) {
                return $headers;
            }
        }

        $headers[] = $name . ': ' . $value;
        return $headers;
    }

    private function statusCodeFromHeaders(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', (string) $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function cacheKey(QueryTemplate $template, Datasource $datasource, array $runtimeParams = []): string
    {
        return 'saiboard:data:' . $template->id . ':' . md5(json_encode([
            'datasource' => $datasource->config,
            'template' => $template->config,
            'type' => $template->dataset_type,
            'params' => $this->cacheableRuntimeParams(
                (string) $template->dataset_type,
                $template->config,
                $runtimeParams,
                $datasource->config
            ),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function cacheableRuntimeParams(
        string $datasetType,
        array $config,
        array $runtimeParams,
        array $datasourceConfig = []
    ): array
    {
        if ($datasetType === 'http_passthrough') {
            $names = $this->httpRuntimeParamNames([
                'datasource_params' => $datasourceConfig['params'] ?? [],
                'path' => $config['path'] ?? '',
                'params' => $config['params'] ?? [],
                'body' => $config['body'] ?? [],
            ]);
            $result = [];
            foreach ($names as $name) {
                if (array_key_exists($name, $runtimeParams)) {
                    $result[$name] = $runtimeParams[$name];
                }
            }
            ksort($result);
            return $result;
        }

        $definitions = $config['params'] ?? [];
        if (!is_array($definitions) || !array_is_list($definitions)) {
            return [];
        }

        $result = [];
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }
            $name = trim((string) ($definition['name'] ?? ''));
            if ($name !== '' && array_key_exists($name, $runtimeParams)) {
                $result[$name] = $runtimeParams[$name];
            }
        }

        ksort($result);
        return $result;
    }

    private function httpRuntimeParamNames(mixed $value): array
    {
        $names = [];
        if (is_array($value)) {
            foreach ($value as $item) {
                $names = array_merge($names, $this->httpRuntimeParamNames($item));
            }

            return array_values(array_unique($names));
        }
        if (is_string($value) && preg_match_all('/\:([A-Za-z][A-Za-z0-9_]*)/', $value, $matches)) {
            return array_values(array_unique($matches[1]));
        }

        return [];
    }

    private function columnKind(string $type): string
    {
        $type = strtolower($type);
        if (preg_match('/int|decimal|double|float|real|numeric|bit|bool/', $type)) {
            return 'number';
        }
        if (preg_match('/date|time|year/', $type)) {
            return 'date';
        }

        return 'string';
    }

    private function safeError(string $message): string
    {
        $message = $this->friendlyError($message);
        $message = preg_replace('/(password|token|secret|authorization|cookie)([^,;\s]*)/i', '$1=***', $message) ?: '执行失败';
        return mb_substr($message, 0, 500);
    }

    private function friendlyError(string $message): string
    {
        if (preg_match("/Unknown database '([^']+)'/i", $message, $matches)) {
            return 'MySQL 数据库不存在：' . $matches[1];
        }
        if (str_contains($message, '[1045]')) {
            return 'MySQL 用户名或密码不正确';
        }
        if (str_contains($message, '[2002]')) {
            return 'MySQL 主机或端口无法连接';
        }
        if (str_contains($message, '[2003]')) {
            return 'MySQL 连接被拒绝，请检查主机和端口';
        }

        return $message;
    }
}
