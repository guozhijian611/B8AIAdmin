<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Controller;

use support\Request;
use support\Response;

class TraceViewController
{
    public function index(Request $request): Response
    {
        if (!config('app.debug', false)) {
            return response('Trace view only available in debug mode.', 404);
        }

        $config = $this->config();
        $traceId = $this->normalizeTraceId((string)$request->get('trace_id', ''));
        $path = trim((string)$request->get('path', ''));
        $limit = $this->clamp((int)$request->get('limit', $config['default_limit']), 10, 200);

        $requestEntries = $this->filterEntries(
            $this->readEntries('request', runtime_path() . '/logs/otel-request-*.log', $config['max_files'], $config['max_lines']),
            $traceId,
            $path
        );
        $sqlEntries = $this->filterSqlEntries(
            $this->readEntries('sql', runtime_path() . '/logs/otel-sql-*.log', $config['max_files'], $config['max_lines']),
            $traceId
        );

        $requestEntries = array_slice($requestEntries, 0, $limit);
        $sqlEntries = $traceId === '' ? [] : array_slice($sqlEntries, 0, $limit * 3);

        return response($this->render($requestEntries, $sqlEntries, $traceId, $path, $limit), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    /** @return array{max_files: int, max_lines: int, default_limit: int} */
    private function config(): array
    {
        $config = (array)config('plugin.openb8.webman-otel-trace.app.trace_view', []);

        return [
            'max_files' => $this->clamp((int)($config['max_files'] ?? 5), 1, 30),
            'max_lines' => $this->clamp((int)($config['max_lines'] ?? 2000), 100, 20000),
            'default_limit' => $this->clamp((int)($config['default_limit'] ?? 50), 10, 200),
        ];
    }

    private function normalizeTraceId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/\b([a-f0-9]{32})\b/i', $value, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return strtolower($value);
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return min($max, max($min, $value));
    }

    /** @return array<int, array<string, mixed>> */
    private function readEntries(string $type, string $pattern, int $maxFiles, int $maxLines): array
    {
        $entries = [];
        foreach ($this->latestFiles($pattern, $maxFiles) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            foreach (array_reverse(array_slice($lines, -$maxLines)) as $line) {
                $entry = $this->parseLine($line, $type, $file);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }
        }

        usort($entries, static function (array $a, array $b): int {
            return strcmp((string)($b['time'] ?? $b['_logged_at'] ?? ''), (string)($a['time'] ?? $a['_logged_at'] ?? ''));
        });

        return $entries;
    }

    /** @return array<int, string> */
    private function latestFiles(string $pattern, int $maxFiles): array
    {
        $files = glob($pattern) ?: [];
        usort($files, static function (string $a, string $b): int {
            return (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0);
        });

        return array_slice($files, 0, $maxFiles);
    }

    /** @return array<string, mixed>|null */
    private function parseLine(string $line, string $type, string $file): ?array
    {
        if (preg_match('/^\[(?<logged_at>[^\]]+)\]\s+(?<channel>[^:]+):\s+(?<message>.*)$/', trim($line), $matches) !== 1) {
            return null;
        }

        $message = preg_replace('/\s+\[\]\s+\[\]\s*$/', '', trim($matches['message']));
        if (!is_string($message) || $message === '') {
            return null;
        }

        $payload = json_decode($message, true);
        if (!is_array($payload)) {
            return null;
        }

        $payload['_type'] = $type;
        $payload['_file'] = basename($file);
        $payload['_logged_at'] = $matches['logged_at'];

        return $payload;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function filterEntries(array $entries, string $traceId, string $path): array
    {
        return array_values(array_filter($entries, function (array $entry) use ($traceId, $path): bool {
            if ($traceId !== '' && strtolower((string)($entry['trace_id'] ?? '')) !== $traceId) {
                return false;
            }

            if ($path === '') {
                return true;
            }

            $entryPath = (string)($entry['path'] ?? '');
            $url = (string)($entry['url'] ?? '');

            return str_contains($entryPath, $path) || str_contains($url, $path);
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function filterSqlEntries(array $entries, string $traceId): array
    {
        if ($traceId === '') {
            return $entries;
        }

        return array_values(array_filter($entries, static function (array $entry) use ($traceId): bool {
            return strtolower((string)($entry['trace_id'] ?? '')) === $traceId;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $requestEntries
     * @param array<int, array<string, mixed>> $sqlEntries
     */
    private function render(array $requestEntries, array $sqlEntries, string $traceId, string $path, int $limit): string
    {
        $sqlLogEnabled = (bool)config('plugin.openb8.webman-otel-trace.app.sql_log.enable', false);
        $requestLogEnabled = (bool)config('plugin.openb8.webman-otel-trace.app.request_log.file', true);
        $traceLabel = $traceId !== '' ? $this->e($traceId) : '最近请求';

        return '<!doctype html>'
            . '<html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Trace Debug</title>' . $this->style() . '</head><body>'
            . '<header><h1>Trace Debug</h1><p>仅 debug 模式可用，读取本地 runtime/logs 下的 otel-request / otel-sql 日志。</p></header>'
            . '<main>'
            . $this->renderSearchForm($traceId, $path, $limit)
            . '<section class="summary"><strong>' . $traceLabel . '</strong><span>HTTP ' . count($requestEntries) . ' 条</span><span>SQL ' . count($sqlEntries) . ' 条</span></section>'
            . (!$requestLogEnabled ? '<p class="warn">request_log.file 当前关闭，页面无法持续读取请求日志。</p>' : '')
            . '<section><h2>HTTP 请求</h2>' . $this->renderRequestTable($requestEntries, $traceId) . '</section>'
            . '<section><h2>SQL 日志</h2>' . $this->renderSqlTable($sqlEntries, $traceId, $sqlLogEnabled) . '</section>'
            . '</main></body></html>';
    }

    private function style(): string
    {
        return '<style>'
            . ':root{color-scheme:light;--bg:#f6f8fb;--panel:#fff;--text:#172033;--muted:#64748b;--line:#d9e1ec;--accent:#2563eb;--ok:#0f766e;--warn:#b45309;--bad:#b91c1c;}'
            . '*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
            . 'header{padding:24px 32px 16px;border-bottom:1px solid var(--line);background:var(--panel);}h1{margin:0 0 4px;font-size:24px;}h2{margin:0 0 12px;font-size:18px;}p{margin:0;color:var(--muted)}main{padding:20px 32px 40px;}'
            . 'form{display:grid;grid-template-columns:minmax(220px,2fr) minmax(160px,1fr) 96px auto;gap:10px;align-items:end;margin-bottom:16px;}label{display:grid;gap:5px;color:var(--muted);font-size:12px;}input{height:36px;border:1px solid var(--line);border-radius:6px;padding:0 10px;background:#fff;color:var(--text);font:inherit;}button,a.button{height:36px;border:0;border-radius:6px;padding:0 14px;background:var(--accent);color:#fff;font:inherit;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;}'
            . '.summary{display:flex;gap:14px;align-items:center;margin:0 0 18px;padding:10px 12px;border:1px solid var(--line);border-radius:8px;background:var(--panel);}.summary span{color:var(--muted)}section{margin-top:20px;}'
            . 'table{width:100%;border-collapse:separate;border-spacing:0;background:var(--panel);border:1px solid var(--line);border-radius:8px;overflow:hidden;}th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;}th{background:#eef3f9;color:#475569;font-size:12px;font-weight:600;}tr:last-child td{border-bottom:0;}'
            . 'code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;word-break:break-all;}pre{margin:8px 0 0;padding:10px;border:1px solid var(--line);border-radius:6px;background:#f8fafc;white-space:pre-wrap;word-break:break-word;max-height:360px;overflow:auto;font:12px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;}'
            . 'details{margin-top:8px;}summary{cursor:pointer;color:var(--accent);}.ok{color:var(--ok)}.warn{color:var(--warn)}.bad{color:var(--bad)}.empty{padding:18px;border:1px dashed var(--line);border-radius:8px;background:var(--panel);color:var(--muted);}'
            . '@media (max-width:760px){header,main{padding-left:14px;padding-right:14px;}form{grid-template-columns:1fr;}table{font-size:12px;display:block;overflow:auto;}}'
            . '</style>';
    }

    private function renderSearchForm(string $traceId, string $path, int $limit): string
    {
        return '<form method="get">'
            . '<label>Trace ID / traceparent<input name="trace_id" value="' . $this->e($traceId) . '" placeholder="粘贴 x-trace-id 或 traceparent"></label>'
            . '<label>Path 过滤<input name="path" value="' . $this->e($path) . '" placeholder="/admin 或 /api"></label>'
            . '<label>Limit<input name="limit" type="number" min="10" max="200" value="' . $limit . '"></label>'
            . '<button type="submit">查询</button>'
            . '</form>';
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function renderRequestTable(array $entries, string $selectedTraceId): string
    {
        if ($entries === []) {
            return '<div class="empty">没有找到请求日志。先访问一个接口，再从 F12 响应头复制 x-trace-id 到上面的输入框。</div>';
        }

        $rows = '';
        foreach ($entries as $entry) {
            $traceId = (string)($entry['trace_id'] ?? '');
            $statusCode = (int)($entry['status_code'] ?? 0);
            $traceCell = $traceId !== ''
                ? '<a href="?trace_id=' . rawurlencode($traceId) . '"><code>' . $this->e($traceId) . '</code></a>'
                : '<span class="warn">无 trace_id</span>';
            $details = $selectedTraceId !== '' ? $this->renderRequestDetails($entry) : '';

            $rows .= '<tr>'
                . '<td>' . $this->e((string)($entry['time'] ?? $entry['_logged_at'] ?? '')) . '</td>'
                . '<td>' . $traceCell . '</td>'
                . '<td>' . $this->e((string)($entry['method'] ?? '')) . '</td>'
                . '<td><code>' . $this->e((string)($entry['path'] ?? '')) . '</code></td>'
                . '<td class="' . $this->statusClass($statusCode) . '">' . $this->e((string)$statusCode) . $this->businessCode($entry) . '</td>'
                . '<td>' . $this->e((string)($entry['duration_ms'] ?? '')) . ' ms</td>'
                . '<td>' . $this->e((string)($entry['client_ip'] ?? '')) . $details . '</td>'
                . '</tr>';
        }

        return '<table><thead><tr><th>时间</th><th>Trace ID</th><th>方法</th><th>路径</th><th>状态</th><th>耗时</th><th>客户端 / 详情</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /** @param array<string, mixed> $entry */
    private function renderRequestDetails(array $entry): string
    {
        return '<details><summary>查看请求/响应</summary>'
            . '<pre>' . $this->pretty([
                'url' => $entry['url'] ?? null,
                'span_id' => $entry['span_id'] ?? null,
                'request' => $entry['request'] ?? null,
                'response' => $entry['response'] ?? null,
                'exception' => $entry['exception'] ?? null,
                'log_file' => $entry['_file'] ?? null,
            ]) . '</pre></details>';
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function renderSqlTable(array $entries, string $traceId, bool $sqlLogEnabled): string
    {
        if ($traceId === '') {
            return '<div class="empty">输入 trace_id 后会展示同一 trace 下的 SQL 文件日志。</div>';
        }

        if ($entries === []) {
            $hint = $sqlLogEnabled
                ? '没有找到这个 trace_id 对应的 SQL 文件日志。'
                : 'sql_log 当前关闭；PDO span 仍可通过 OTLP/Jaeger 等后端查看。需要在本页面看 SQL 时开启 OTEL_SQL_LOG=true 或 sql_log.enable=true。';

            return '<div class="empty">' . $this->e($hint) . '</div>';
        }

        $rows = '';
        foreach ($entries as $entry) {
            $rows .= '<tr>'
                . '<td>' . $this->e((string)($entry['time'] ?? $entry['_logged_at'] ?? '')) . '</td>'
                . '<td><code>' . $this->e((string)($entry['span_id'] ?? '')) . '</code></td>'
                . '<td>' . $this->e((string)($entry['runtime_seconds'] ?? '')) . ' s</td>'
                . '<td><pre>' . $this->e((string)($entry['sql'] ?? '')) . '</pre></td>'
                . '</tr>';
        }

        return '<table><thead><tr><th>时间</th><th>Span ID</th><th>耗时</th><th>SQL</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /** @param array<string, mixed> $entry */
    private function businessCode(array $entry): string
    {
        if (!array_key_exists('business_code', $entry) || $entry['business_code'] === null || $entry['business_code'] === '') {
            return '';
        }

        return '<br><code>code=' . $this->e((string)$entry['business_code']) . '</code>';
    }

    private function statusClass(int $statusCode): string
    {
        if ($statusCode >= 500) {
            return 'bad';
        }
        if ($statusCode >= 400) {
            return 'warn';
        }

        return 'ok';
    }

    private function pretty(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return $this->e(is_string($json) ? $json : '');
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
