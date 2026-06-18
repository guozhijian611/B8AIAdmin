<?php

namespace plugin\saiboard\app\service;

use InvalidArgumentException;
use PDO;
use plugin\saiboard\app\model\Datasource;
use plugin\saiboard\app\model\QueryTemplate;
use support\think\Cache;
use Throwable;

class DataSourceExecutor
{
    public function testDatasource(Datasource $datasource): array
    {
        return match ((string) $datasource->type) {
            'mysql' => $this->testMysql($datasource->config),
            'http' => $this->executeHttp($datasource->config, ['dataset_type' => 'http_passthrough', 'config' => []]),
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

    public function execute(QueryTemplate $template, bool $forceRefresh = false, array $runtimeParams = []): array
    {
        $datasource = Datasource::where('id', (int) $template->datasource_id)
            ->where('status', 1)
            ->findOrEmpty();
        if ($datasource->isEmpty()) {
            throw new InvalidArgumentException('数据源不存在或已停用');
        }

        $cacheTtl = max(0, (int) $datasource->cache_ttl);
        $cacheKey = $this->cacheKey($template, $datasource, $runtimeParams);
        if (!$forceRefresh && $cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

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
                ]),
                default => throw new InvalidArgumentException('数据源类型不支持'),
            };

            if ($cacheTtl > 0) {
                Cache::set($cacheKey, $result, $cacheTtl);
            }
            if ((string) $datasource->last_error !== '') {
                $datasource->save(['last_error' => null]);
            }

            return $result;
        } catch (Throwable $exception) {
            $datasource->save(['last_error' => $this->safeError($exception->getMessage())]);
            throw $exception;
        }
    }

    private function testMysql(array $config): array
    {
        $pdo = $this->pdo($config);
        $row = $pdo->query('SELECT 1 AS ok')->fetch(PDO::FETCH_ASSOC);
        return ['rows' => [$row], 'total' => 1];
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

    private function executeHttp(array $datasourceConfig, array $template): array
    {
        $templateConfig = is_array($template['config'] ?? null) ? $template['config'] : [];
        $url = $this->buildHttpUrl($datasourceConfig, $templateConfig);
        $this->assertPublicHttpUrl($url);
        $headers = $this->normalizeHeaders($datasourceConfig['headers'] ?? []);
        $payload = $this->requestJson($url, $headers);

        if (isset($payload['rows']) && is_array($payload['rows'])) {
            return [
                'rows' => $payload['rows'],
                'total' => (int) ($payload['total'] ?? count($payload['rows'])),
            ];
        }

        $data = $payload['data'] ?? $payload;
        if (is_array($data) && array_is_list($data)) {
            return ['rows' => $data, 'total' => count($data)];
        }

        return ['rows' => [$data], 'total' => 1];
    }

    private function buildHttpUrl(array $datasourceConfig, array $templateConfig): string
    {
        $baseUrl = trim((string) ($datasourceConfig['url'] ?? ''));
        if ($baseUrl === '') {
            throw new InvalidArgumentException('HTTP 数据源 URL 必须填写');
        }

        $path = trim((string) ($templateConfig['path'] ?? ''));
        $url = $path === '' ? $baseUrl : rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        $params = array_merge(
            is_array($datasourceConfig['params'] ?? null) ? $datasourceConfig['params'] : [],
            is_array($templateConfig['params'] ?? null) ? $templateConfig['params'] : []
        );
        if ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }

        return $url;
    }

    private function assertPublicHttpUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('HTTP 数据源 URL 不正确');
        }
        if (in_array($host, ['localhost', 'localhost.localdomain'], true)) {
            throw new InvalidArgumentException('HTTP 数据源不允许访问本机地址');
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
            throw new InvalidArgumentException('HTTP 数据源域名无法解析');
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('HTTP 数据源不允许访问内网或保留地址');
            }
        }
    }

    private function normalizeHeaders(mixed $headers): array
    {
        if (!is_array($headers)) {
            return [];
        }

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

    private function requestJson(string $url, array $headers): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            if ($body === false || $status >= 400) {
                throw new InvalidArgumentException($error ?: 'HTTP 数据源请求失败');
            }
        } else {
            $body = file_get_contents($url, false, stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'header' => implode("\r\n", $headers),
                    'ignore_errors' => true,
                ],
            ]));
            if ($body === false) {
                throw new InvalidArgumentException('HTTP 数据源请求失败');
            }
        }

        $payload = json_decode((string) $body, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('HTTP 数据源返回内容不是 JSON');
        }

        return $payload;
    }

    private function cacheKey(QueryTemplate $template, Datasource $datasource, array $runtimeParams = []): string
    {
        return 'saiboard:data:' . $template->id . ':' . md5(json_encode([
            'datasource' => $datasource->config,
            'template' => $template->config,
            'type' => $template->dataset_type,
            'params' => $this->cacheableRuntimeParams($template->config, $runtimeParams),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function cacheableRuntimeParams(array $config, array $runtimeParams): array
    {
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
        $message = preg_replace('/(password|token|secret|authorization|cookie)([^,;\s]*)/i', '$1=***', $message) ?: '执行失败';
        return mb_substr($message, 0, 500);
    }
}
