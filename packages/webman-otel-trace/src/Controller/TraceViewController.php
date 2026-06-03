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
        $page = max(1, (int)$request->get('page', 1));

        $requestEntries = $this->filterEntries(
            $this->readEntries('request', runtime_path() . '/logs/otel-request-*.log', $config['max_files'], $config['max_lines']),
            $traceId,
            $path
        );
        $sqlEntries = $this->filterSqlEntries(
            $this->readEntries('sql', runtime_path() . '/logs/otel-sql-*.log', $config['max_files'], $config['max_lines']),
            $traceId
        );
        $spanEntries = $this->filterSpanEntries(
            $this->readEntries('span', runtime_path() . '/logs/otel-span-*.log', $config['max_files'], $config['max_lines']),
            $traceId
        );

        $requestTotal = count($requestEntries);
        $pageCount = max(1, (int)ceil($requestTotal / $limit));
        $page = $traceId === '' ? min($page, $pageCount) : 1;
        $requestEntries = array_slice($requestEntries, ($page - 1) * $limit, $limit);
        $sqlEntries = $traceId === '' ? [] : array_slice($sqlEntries, 0, $limit * 3);
        $spanEntries = $traceId === '' ? [] : array_slice($spanEntries, 0, $limit * 3);

        return response($this->render($requestEntries, $sqlEntries, $spanEntries, $traceId, $path, $limit, $page, $requestTotal), 200, [
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

            $fileMtime = filemtime($file) ?: 0;
            foreach (array_slice($lines, -$maxLines, null, true) as $lineIndex => $line) {
                $entry = $this->parseLine($line, $type, $file, (int)$lineIndex + 1, $fileMtime);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }
        }

        usort($entries, function (array $a, array $b): int {
            $timeCompare = $this->loggedTimestamp($b) <=> $this->loggedTimestamp($a);
            if ($timeCompare !== 0) {
                return $timeCompare;
            }

            $fileCompare = (int)($b['_file_mtime'] ?? 0) <=> (int)($a['_file_mtime'] ?? 0);
            if ($fileCompare !== 0) {
                return $fileCompare;
            }

            return (int)($b['_line_no'] ?? 0) <=> (int)($a['_line_no'] ?? 0);
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
    private function parseLine(string $line, string $type, string $file, int $lineNumber, int $fileMtime): ?array
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
        $payload['_file_mtime'] = $fileMtime;
        $payload['_line_no'] = $lineNumber;
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
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function filterSpanEntries(array $entries, string $traceId): array
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
     * @param array<int, array<string, mixed>> $spanEntries
     */
    private function render(array $requestEntries, array $sqlEntries, array $spanEntries, string $traceId, string $path, int $limit, int $page, int $requestTotal): string
    {
        $sqlLogEnabled = (bool)config('plugin.openb8.webman-otel-trace.app.sql_log.enable', false);
        $requestLogEnabled = (bool)config('plugin.openb8.webman-otel-trace.app.request_log.file', true);
        $spanLogEnabled = (bool)config('plugin.openb8.webman-otel-trace.app.business_span.file', true);
        $traceLabel = $traceId !== '' ? $this->e($traceId) : '最近请求';
        $pageCount = max(1, (int)ceil($requestTotal / $limit));
        $requestTitle = $traceId === '' ? 'Trace 列表' : 'HTTP 明细';
        $sqlSummary = $traceId !== '' ? '<span>SQL ' . count($sqlEntries) . ' 条</span>' : '';
        $spanSummary = $traceId !== '' ? '<span>业务 Span ' . count($spanEntries) . ' 条</span>' : '';
        $pageSummary = $traceId === '' ? '<span>第 ' . $page . ' / ' . $pageCount . ' 页</span>' : '';

        return '<!doctype html>'
            . '<html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Trace Debug</title>' . $this->style() . '</head><body>'
            . '<header><h1>Trace Debug</h1><p>仅 debug 模式可用，读取本地 runtime/logs 下的 otel-request / otel-span / otel-sql 日志。</p></header>'
            . '<main>'
            . $this->renderSearchForm($traceId, $path, $limit)
            . '<section class="summary"><strong>' . $traceLabel . '</strong><span>HTTP ' . $requestTotal . ' 条</span>' . $spanSummary . $sqlSummary . $pageSummary . '</section>'
            . (!$requestLogEnabled ? '<p class="warn">request_log.file 当前关闭，页面无法持续读取请求日志。</p>' : '')
            . (!$spanLogEnabled ? '<p class="warn">business_span.file 当前关闭，页面无法读取业务 span 本地日志。</p>' : '')
            . ($traceId !== '' ? '<section><h2>Trace 详情时间线</h2>' . $this->renderTimeline($requestEntries, $sqlEntries, $spanEntries, $traceId) . '</section>' : '')
            . '<section><h2>' . $requestTitle . '</h2>' . $this->renderRequestTable($requestEntries) . ($traceId === '' ? $this->renderPagination($traceId, $path, $limit, $page, $requestTotal) : '') . '</section>'
            . ($traceId !== '' ? '<section><h2>业务 Span 明细</h2>' . $this->renderSpanTable($spanEntries, $traceId, $spanLogEnabled) . '</section>' : '')
            . ($traceId !== '' ? '<section><h2>SQL 明细</h2>' . $this->renderSqlTable($sqlEntries, $traceId, $sqlLogEnabled) . '</section>' : '')
            . '</main></body></html>';
    }

    private function style(): string
    {
        return '<style>'
            . ':root{color-scheme:light;--bg:#f6f8fb;--panel:#fff;--text:#172033;--muted:#64748b;--line:#d9e1ec;--accent:#2563eb;--ok:#0f766e;--warn:#b45309;--bad:#b91c1c;}'
            . '*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
            . 'header{padding:26px 32px 18px;border-bottom:1px solid var(--line);background:linear-gradient(180deg,#fff 0%,#f9fbff 100%);}h1{margin:0 0 4px;font-size:26px;}h2{margin:0 0 12px;font-size:18px;}p{margin:0;color:var(--muted)}main{padding:20px 32px 40px;max-width:1440px;}'
            . 'form{display:grid;grid-template-columns:minmax(220px,2fr) minmax(160px,1fr) 96px auto;gap:10px;align-items:end;margin-bottom:16px;padding:14px;border:1px solid var(--line);border-radius:8px;background:var(--panel);}label{display:grid;gap:5px;color:var(--muted);font-size:12px;}input{height:36px;border:1px solid var(--line);border-radius:6px;padding:0 10px;background:#fff;color:var(--text);font:inherit;}button,a.button{height:36px;border:0;border-radius:6px;padding:0 14px;background:var(--accent);color:#fff;font:inherit;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;}'
            . '.summary{display:flex;gap:14px;align-items:center;margin:0 0 18px;padding:10px 12px;border:1px solid var(--line);border-radius:8px;background:var(--panel);}.summary span{color:var(--muted)}section{margin-top:20px;}'
            . '.timeline{position:relative;margin:0;padding:2px 0 2px 18px;border-left:2px solid #c7d7ee;}.timeline-item{position:relative;margin:0 0 12px;padding:12px 14px;border:1px solid var(--line);border-radius:8px;background:var(--panel);box-shadow:0 8px 22px rgba(15,23,42,.05);}.timeline-item:before{content:"";position:absolute;left:-25px;top:18px;width:12px;height:12px;border-radius:999px;background:var(--accent);box-shadow:0 0 0 4px #e7efff;}.timeline-item.span:before{background:#0891b2;box-shadow:0 0 0 4px #cffafe;}.timeline-item.sql:before{background:#7c3aed;box-shadow:0 0 0 4px #f0e8ff;}.timeline-item.bad:before{background:var(--bad);box-shadow:0 0 0 4px #fee2e2;}.timeline-head{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-bottom:8px;}.timeline-title{display:flex;gap:8px;align-items:center;min-width:0;}.timeline-meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap;color:var(--muted);font-size:12px;}.badge{display:inline-flex;align-items:center;height:22px;border-radius:999px;padding:0 8px;background:#e8f0ff;color:#1d4ed8;font-size:12px;font-weight:600;}.badge.span{background:#cffafe;color:#0e7490}.badge.sql{background:#f2e8ff;color:#6d28d9}.badge.ok{background:#dcfce7;color:#166534}.badge.warn{background:#fef3c7;color:#92400e}.badge.bad{background:#fee2e2;color:#991b1b}.timeline-main{display:flex;gap:10px;align-items:baseline;min-width:0;}.timeline-main code{font-size:13px;}.timeline-sub{color:var(--muted);font-size:12px;word-break:break-word;}'
            . '.pagination{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:12px;flex-wrap:wrap;color:var(--muted);}.pagination a,.pagination span.control{height:30px;min-width:30px;border:1px solid var(--line);border-radius:6px;padding:0 10px;background:#fff;color:var(--text);display:inline-flex;align-items:center;justify-content:center;text-decoration:none;}.pagination .disabled{opacity:.45}.pagination .page-state{margin-right:auto;}'
            . '.request-details{margin-top:10px;}.request-details>summary{display:inline-flex;gap:8px;align-items:center;color:var(--accent);font-weight:600;}.request-details>summary small{color:var(--muted);font-weight:400}.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px;}.detail-block{border:1px solid var(--line);border-radius:8px;background:#fff;overflow:hidden;}.detail-block.wide{grid-column:1/-1}.detail-title{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;background:#f8fafc;border-bottom:1px solid var(--line);font-weight:700;font-size:12px;color:#475569}.detail-body{padding:10px}.kv{display:grid;grid-template-columns:minmax(96px,160px) minmax(0,1fr);gap:0;border:1px solid var(--line);border-bottom:0;border-radius:6px;overflow:hidden}.kv dt,.kv dd{margin:0;padding:7px 8px;border-bottom:1px solid var(--line);}.kv dt{background:#f8fafc;color:var(--muted);font-size:12px}.kv dd{word-break:break-word}.detail-empty{color:var(--muted);font-size:12px}.payload-text{max-height:260px}.payload-json{max-height:300px}'
            . 'table{width:100%;border-collapse:separate;border-spacing:0;background:var(--panel);border:1px solid var(--line);border-radius:8px;overflow:hidden;}th,td{padding:9px 10px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top;}th{background:#eef3f9;color:#475569;font-size:12px;font-weight:600;}tr:last-child td{border-bottom:0;}'
            . 'code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;word-break:break-all;}pre{margin:8px 0 0;padding:10px;border:1px solid var(--line);border-radius:6px;background:#f8fafc;white-space:pre-wrap;word-break:break-word;max-height:360px;overflow:auto;font:12px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;}'
            . 'details{margin-top:8px;}summary{cursor:pointer;color:var(--accent);}.ok{color:var(--ok)}.warn{color:var(--warn)}.bad{color:var(--bad)}.empty{padding:18px;border:1px dashed var(--line);border-radius:8px;background:var(--panel);color:var(--muted);}'
            . '@media (max-width:760px){header,main{padding-left:14px;padding-right:14px;}form{grid-template-columns:1fr;}.timeline-head,.timeline-main{align-items:flex-start;flex-direction:column;gap:6px;}.detail-grid{grid-template-columns:1fr}.kv{grid-template-columns:1fr}.pagination{justify-content:flex-start}.pagination .page-state{width:100%;}table{font-size:12px;display:block;overflow:auto;}}'
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

    private function renderPagination(string $traceId, string $path, int $limit, int $page, int $total): string
    {
        $pageCount = max(1, (int)ceil($total / $limit));
        if ($pageCount <= 1) {
            return '';
        }

        $prev = $page - 1;
        $next = $page + 1;

        return '<nav class="pagination" aria-label="Trace 列表分页">'
            . '<span class="page-state">共 ' . $total . ' 条，每页 ' . $limit . ' 条，第 ' . $page . ' / ' . $pageCount . ' 页</span>'
            . ($page > 1
                ? '<a href="' . $this->pageUrl($traceId, $path, $limit, 1) . '">首页</a><a href="' . $this->pageUrl($traceId, $path, $limit, $prev) . '">上一页</a>'
                : '<span class="control disabled">首页</span><span class="control disabled">上一页</span>')
            . ($page < $pageCount
                ? '<a href="' . $this->pageUrl($traceId, $path, $limit, $next) . '">下一页</a><a href="' . $this->pageUrl($traceId, $path, $limit, $pageCount) . '">尾页</a>'
                : '<span class="control disabled">下一页</span><span class="control disabled">尾页</span>')
            . '</nav>';
    }

    private function pageUrl(string $traceId, string $path, int $limit, int $page): string
    {
        $params = [
            'limit' => $limit,
            'page' => $page,
        ];
        if ($traceId !== '') {
            $params['trace_id'] = $traceId;
        }
        if ($path !== '') {
            $params['path'] = $path;
        }

        return '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<int, array<string, mixed>> $requestEntries
     * @param array<int, array<string, mixed>> $sqlEntries
     * @param array<int, array<string, mixed>> $spanEntries
     */
    private function renderTimeline(array $requestEntries, array $sqlEntries, array $spanEntries, string $traceId): string
    {
        if ($requestEntries === [] && $sqlEntries === [] && $spanEntries === []) {
            return $traceId === ''
                ? '<div class="empty">暂无可展示的请求时间线。先访问一个接口，或者输入 trace_id 查询单次请求链路。</div>'
                : '<div class="empty">没有找到这个 trace_id 对应的时间线事件。</div>';
        }

        $events = [];
        foreach ($requestEntries as $entry) {
            $entry['_timeline_type'] = 'http';
            $events[] = $entry;
        }
        foreach ($sqlEntries as $entry) {
            $entry['_timeline_type'] = 'sql';
            $events[] = $entry;
        }
        foreach ($spanEntries as $entry) {
            $entry['_timeline_type'] = 'span';
            $events[] = $entry;
        }

        usort($events, function (array $a, array $b): int {
            $timeCompare = $this->timelineTimestamp($a) <=> $this->timelineTimestamp($b);
            if ($timeCompare !== 0) {
                return $timeCompare;
            }

            $typeCompare = $this->timelineTypeRank($a) <=> $this->timelineTypeRank($b);
            if ($typeCompare !== 0) {
                return $typeCompare;
            }

            $fileCompare = (int)($a['_file_mtime'] ?? 0) <=> (int)($b['_file_mtime'] ?? 0);
            if ($fileCompare !== 0) {
                return $fileCompare;
            }

            return (int)($a['_line_no'] ?? 0) <=> (int)($b['_line_no'] ?? 0);
        });

        $items = '';
        foreach ($events as $entry) {
            $items .= $this->renderTimelineItem($entry);
        }

        return '<div class="timeline">' . $items . '</div>';
    }

    /** @param array<string, mixed> $entry */
    private function renderTimelineItem(array $entry): string
    {
        $type = (string)($entry['_timeline_type'] ?? 'http');
        $time = $this->e((string)($entry['time'] ?? $entry['_logged_at'] ?? ''));

        if ($type === 'sql') {
            return '<article class="timeline-item sql">'
                . '<div class="timeline-head"><div class="timeline-title"><span class="badge sql">SQL</span><strong>' . $time . '</strong></div><div class="timeline-meta"><span>' . $this->durationLabel($entry) . '</span><code>' . $this->e((string)($entry['span_id'] ?? '')) . '</code></div></div>'
                . '<div class="timeline-main"><code>' . $this->e($this->sqlSummary((string)($entry['sql'] ?? ''))) . '</code></div>'
                . '<div class="timeline-sub">文件：' . $this->e((string)($entry['_file'] ?? '')) . '</div>'
                . $this->renderSqlDetails($entry)
                . '</article>';
        }

        if ($type === 'span') {
            $status = (string)($entry['status'] ?? 'Ok');
            $class = $status === 'Error' ? 'bad' : 'ok';
            $name = $this->e((string)($entry['name'] ?? 'business.span'));
            $kind = $this->e((string)($entry['kind'] ?? 'internal'));

            return '<article class="timeline-item span ' . $class . '">'
                . '<div class="timeline-head"><div class="timeline-title"><span class="badge span">SPAN</span><strong>' . $time . '</strong></div><div class="timeline-meta"><span class="badge ' . $class . '">' . $this->e($status) . '</span><span>' . $this->durationLabel($entry) . '</span><code>' . $this->e((string)($entry['span_id'] ?? '')) . '</code></div></div>'
                . '<div class="timeline-main"><strong>' . $name . '</strong><span class="timeline-sub">' . $kind . '</span></div>'
                . '<div class="timeline-sub">父 Span：<code>' . $this->e((string)($entry['parent_span_id'] ?? '')) . '</code></div>'
                . $this->renderSpanDetails($entry)
                . '</article>';
        }

        $statusCode = (int)($entry['status_code'] ?? 0);
        $class = $this->statusClass($statusCode);
        $method = $this->e((string)($entry['method'] ?? 'HTTP'));
        $path = $this->e((string)($entry['path'] ?? ''));

        return '<article class="timeline-item ' . $class . '">'
            . '<div class="timeline-head"><div class="timeline-title"><span class="badge">HTTP</span><strong>' . $time . '</strong></div><div class="timeline-meta"><span class="badge ' . $class . '">' . $this->e((string)$statusCode) . '</span><span>' . $this->durationLabel($entry) . '</span></div></div>'
            . '<div class="timeline-main"><strong>' . $method . '</strong><code>' . $path . '</code></div>'
            . '<div class="timeline-sub">' . $this->httpSummary($entry) . '</div>'
            . $this->renderRequestDetails($entry)
            . '</article>';
    }

    /** @param array<string, mixed> $entry */
    private function loggedTimestamp(array $entry): int
    {
        $time = (string)($entry['time'] ?? $entry['_logged_at'] ?? '');
        $timestamp = strtotime($time);

        return $timestamp === false ? 0 : $timestamp;
    }

    /** @param array<string, mixed> $entry */
    private function timelineTimestamp(array $entry): float
    {
        if (array_key_exists('timestamp', $entry)) {
            return (float)$entry['timestamp'];
        }

        $timestamp = (float)$this->loggedTimestamp($entry);
        if (($entry['_timeline_type'] ?? '') === 'http' && array_key_exists('duration_ms', $entry)) {
            return $timestamp - ((float)$entry['duration_ms'] / 1000);
        }

        return $timestamp;
    }

    /** @param array<string, mixed> $entry */
    private function timelineTypeRank(array $entry): int
    {
        return match ($entry['_timeline_type'] ?? '') {
            'http' => 0,
            'span' => 1,
            default => 2,
        };
    }

    /** @param array<string, mixed> $entry */
    private function durationLabel(array $entry): string
    {
        if (array_key_exists('duration_ms', $entry)) {
            return $this->e((string)$entry['duration_ms']) . ' ms';
        }

        if (array_key_exists('runtime_seconds', $entry)) {
            $milliseconds = round((float)$entry['runtime_seconds'] * 1000, 2);
            return $this->e((string)$milliseconds) . ' ms';
        }

        return '耗时未知';
    }

    /** @param array<string, mixed> $entry */
    private function httpSummary(array $entry): string
    {
        $parts = [];
        $traceId = (string)($entry['trace_id'] ?? '');
        if ($traceId !== '') {
            $parts[] = 'Trace: <code>' . $this->e($traceId) . '</code>';
        }
        if (($entry['client_ip'] ?? '') !== '') {
            $parts[] = 'IP: ' . $this->e((string)$entry['client_ip']);
        }
        if (array_key_exists('business_code', $entry) && $entry['business_code'] !== null && $entry['business_code'] !== '') {
            $parts[] = '业务 code: <code>' . $this->e((string)$entry['business_code']) . '</code>';
        }

        return $parts === [] ? '暂无更多摘要信息' : implode(' · ', $parts);
    }

    private function sqlSummary(string $sql): string
    {
        $sql = trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);
        if (strlen($sql) <= 180) {
            return $sql;
        }

        return substr($sql, 0, 180) . '...';
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function renderRequestTable(array $entries): string
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

            $rows .= '<tr>'
                . '<td>' . $this->e((string)($entry['time'] ?? $entry['_logged_at'] ?? '')) . '</td>'
                . '<td>' . $traceCell . '</td>'
                . '<td>' . $this->e((string)($entry['method'] ?? '')) . '</td>'
                . '<td><code>' . $this->e((string)($entry['path'] ?? '')) . '</code></td>'
                . '<td class="' . $this->statusClass($statusCode) . '">' . $this->e((string)$statusCode) . $this->businessCode($entry) . '</td>'
                . '<td>' . $this->e((string)($entry['duration_ms'] ?? '')) . ' ms</td>'
                . '<td>' . $this->e((string)($entry['client_ip'] ?? '')) . '</td>'
                . '</tr>';
        }

        return '<table><thead><tr><th>时间</th><th>Trace ID</th><th>方法</th><th>路径</th><th>状态</th><th>耗时</th><th>客户端</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /** @param array<string, mixed> $entry */
    private function renderRequestDetails(array $entry): string
    {
        return '<details class="request-details"><summary><span>查看请求/响应</span><small>分区查看</small></summary>'
            . '<div class="detail-grid">'
            . $this->renderPayloadBlock('基础信息', [
                'URL' => $entry['url'] ?? null,
                'Span ID' => $entry['span_id'] ?? null,
                '客户端 IP' => $entry['client_ip'] ?? null,
                '日志文件' => $entry['_file'] ?? null,
            ])
            . $this->renderPayloadBlock('请求头', $entry['headers'] ?? null)
            . $this->renderPayloadBlock('请求参数', $entry['request'] ?? null)
            . $this->renderPayloadBlock('响应内容', $entry['response'] ?? null, 'wide')
            . $this->renderPayloadBlock('异常信息', $entry['exception'] ?? null, 'wide')
            . '</div></details>';
    }

    /** @param array<string, mixed> $entry */
    private function renderSqlDetails(array $entry): string
    {
        return '<details class="request-details"><summary><span>查看 SQL</span><small>完整语句</small></summary>'
            . '<div class="detail-grid">'
            . $this->renderPayloadBlock('SQL 信息', [
                'Span ID' => $entry['span_id'] ?? null,
                '耗时' => array_key_exists('runtime_seconds', $entry) ? ((string)$entry['runtime_seconds'] . ' s') : null,
                '日志文件' => $entry['_file'] ?? null,
            ])
            . $this->renderPayloadBlock('SQL 语句', $entry['sql'] ?? null, 'wide')
            . '</div></details>';
    }

    /** @param array<string, mixed> $entry */
    private function renderSpanDetails(array $entry): string
    {
        return '<details class="request-details"><summary><span>查看业务 Span</span><small>属性/事件</small></summary>'
            . '<div class="detail-grid">'
            . $this->renderPayloadBlock('Span 信息', [
                'Trace ID' => $entry['trace_id'] ?? null,
                'Span ID' => $entry['span_id'] ?? null,
                '父 Span ID' => $entry['parent_span_id'] ?? null,
                '类型' => $entry['kind'] ?? null,
                '状态' => $entry['status'] ?? null,
                '状态说明' => $entry['status_description'] ?? null,
                '日志文件' => $entry['_file'] ?? null,
            ])
            . $this->renderPayloadBlock('属性', $entry['attributes'] ?? null)
            . $this->renderPayloadBlock('事件', $entry['events'] ?? null, 'wide')
            . $this->renderPayloadBlock('异常信息', $entry['exception'] ?? null, 'wide')
            . '</div></details>';
    }

    private function renderPayloadBlock(string $title, mixed $value, string $class = ''): string
    {
        $value = $this->decodePayload($value);
        $body = $this->isEmptyPayload($value)
            ? '<div class="detail-empty">无</div>'
            : $this->renderPayloadBody($value);

        return '<section class="detail-block ' . $this->e($class) . '">'
            . '<div class="detail-title">' . $this->e($title) . '</div>'
            . '<div class="detail-body">' . $body . '</div>'
            . '</section>';
    }

    private function renderPayloadBody(mixed $value): string
    {
        if (is_array($value) && $this->isAssoc($value)) {
            $rows = '';
            foreach ($value as $key => $item) {
                $rows .= '<dt>' . $this->e((string)$key) . '</dt><dd>' . $this->renderInlineValue($item) . '</dd>';
            }

            return '<dl class="kv">' . $rows . '</dl>';
        }

        if (is_array($value)) {
            return '<pre class="payload-json">' . $this->pretty($value) . '</pre>';
        }

        return '<pre class="payload-text">' . $this->e((string)$value) . '</pre>';
    }

    private function renderInlineValue(mixed $value): string
    {
        $value = $this->decodePayload($value);
        if ($this->isEmptyPayload($value)) {
            return '<span class="detail-empty">无</span>';
        }

        if (is_array($value)) {
            return '<pre class="payload-json">' . $this->pretty($value) . '</pre>';
        }

        if (is_bool($value)) {
            return '<code>' . ($value ? 'true' : 'false') . '</code>';
        }

        return '<code>' . $this->e((string)$value) . '</code>';
    }

    private function decodePayload(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || !in_array($trimmed[0], ['{', '['], true)) {
            return $value;
        }

        $decoded = json_decode($trimmed, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function isEmptyPayload(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** @param array<mixed> $value */
    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function renderSpanTable(array $entries, string $traceId, bool $spanLogEnabled): string
    {
        if ($traceId === '') {
            return '<div class="empty">输入 trace_id 后会展示同一 trace 下的业务 span 文件日志。</div>';
        }

        if ($entries === []) {
            $hint = $spanLogEnabled
                ? '没有找到这个 trace_id 对应的业务 span。请确认业务代码是否调用 Trace::span()。'
                : 'business_span.file 当前关闭；业务 span 不会写入本地页面日志。需要展示时开启 OTEL_BUSINESS_SPAN_FILE=true。';

            return '<div class="empty">' . $this->e($hint) . '</div>';
        }

        $rows = '';
        foreach ($entries as $entry) {
            $status = (string)($entry['status'] ?? 'Ok');
            $rows .= '<tr>'
                . '<td>' . $this->e((string)($entry['time'] ?? $entry['_logged_at'] ?? '')) . '</td>'
                . '<td><code>' . $this->e((string)($entry['span_id'] ?? '')) . '</code></td>'
                . '<td><code>' . $this->e((string)($entry['parent_span_id'] ?? '')) . '</code></td>'
                . '<td><strong>' . $this->e((string)($entry['name'] ?? '')) . '</strong><br><code>' . $this->e((string)($entry['kind'] ?? 'internal')) . '</code></td>'
                . '<td class="' . ($status === 'Error' ? 'bad' : 'ok') . '">' . $this->e($status) . '</td>'
                . '<td>' . $this->e((string)($entry['duration_ms'] ?? '')) . ' ms</td>'
                . '<td>' . $this->compactAttributes($entry['attributes'] ?? []) . '</td>'
                . '</tr>';
        }

        return '<table><thead><tr><th>时间</th><th>Span ID</th><th>父 Span</th><th>名称</th><th>状态</th><th>耗时</th><th>属性</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /** @param mixed $attributes */
    private function compactAttributes(mixed $attributes): string
    {
        $attributes = $this->decodePayload($attributes);
        if (!is_array($attributes) || $attributes === []) {
            return '<span class="detail-empty">无</span>';
        }

        $items = [];
        foreach (array_slice($attributes, 0, 6, true) as $key => $value) {
            $items[] = '<code>' . $this->e((string)$key) . '=' . $this->e($this->inlineText($value)) . '</code>';
        }

        $suffix = count($attributes) > 6 ? '<br><span class="detail-empty">还有 ' . (count($attributes) - 6) . ' 项</span>' : '';

        return implode('<br>', $items) . $suffix;
    }

    private function inlineText(mixed $value): string
    {
        $value = $this->decodePayload($value);
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : 'array';
        }

        $text = (string)$value;
        return strlen($text) > 80 ? substr($text, 0, 80) . '...' : $text;
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
