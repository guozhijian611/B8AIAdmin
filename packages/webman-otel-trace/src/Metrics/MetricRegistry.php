<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Metrics;

class MetricRegistry
{
    /** @var array<string, array{labels: array<string, string>, value: int}> */
    private static array $httpRequests = [];

    /** @var array<string, array{labels: array<string, string>, count: int, sum: float}> */
    private static array $httpDurations = [];

    /** @var array<string, array{labels: array<string, string>, value: int}> */
    private static array $rabbitmqMessages = [];

    public static function recordHttp(string $method, string $path, int $status, float $durationSeconds): void
    {
        $labels = [
            'method' => strtoupper($method),
            'path' => self::normalizePath($path),
            'status' => (string)$status,
        ];
        $key = self::key($labels);

        self::$httpRequests[$key] ??= ['labels' => $labels, 'value' => 0];
        self::$httpRequests[$key]['value']++;

        self::$httpDurations[$key] ??= ['labels' => $labels, 'count' => 0, 'sum' => 0.0];
        self::$httpDurations[$key]['count']++;
        self::$httpDurations[$key]['sum'] += max(0.0, $durationSeconds);
    }

    public static function recordRabbitmq(string $operation, string $destination, string $status): void
    {
        $labels = [
            'operation' => $operation,
            'destination' => $destination,
            'status' => $status,
        ];
        $key = self::key($labels);

        self::$rabbitmqMessages[$key] ??= ['labels' => $labels, 'value' => 0];
        self::$rabbitmqMessages[$key]['value']++;
    }

    public static function render(): string
    {
        $lines = [
            '# HELP webman_http_requests_total Total HTTP requests observed by webman-otel-trace.',
            '# TYPE webman_http_requests_total counter',
        ];

        foreach (self::$httpRequests as $metric) {
            $lines[] = 'webman_http_requests_total' . self::labels($metric['labels']) . ' ' . $metric['value'];
        }

        $lines[] = '# HELP webman_http_request_duration_seconds HTTP request duration in seconds.';
        $lines[] = '# TYPE webman_http_request_duration_seconds summary';
        foreach (self::$httpDurations as $metric) {
            $lines[] = 'webman_http_request_duration_seconds_count' . self::labels($metric['labels']) . ' ' . $metric['count'];
            $lines[] = 'webman_http_request_duration_seconds_sum' . self::labels($metric['labels']) . ' ' . self::float($metric['sum']);
        }

        $lines[] = '# HELP webman_rabbitmq_messages_total RabbitMQ publish/consume operations observed by webman-otel-trace.';
        $lines[] = '# TYPE webman_rabbitmq_messages_total counter';
        foreach (self::$rabbitmqMessages as $metric) {
            $lines[] = 'webman_rabbitmq_messages_total' . self::labels($metric['labels']) . ' ' . $metric['value'];
        }

        return implode("\n", $lines) . "\n";
    }

    private static function normalizePath(string $path): string
    {
        return $path === '' ? '/' : $path;
    }

    /** @param array<string, string> $labels */
    private static function key(array $labels): string
    {
        ksort($labels);
        return http_build_query($labels, '', '&');
    }

    /** @param array<string, string> $labels */
    private static function labels(array $labels): string
    {
        $parts = [];
        foreach ($labels as $name => $value) {
            $parts[] = $name . '="' . self::escape($value) . '"';
        }
        return '{' . implode(',', $parts) . '}';
    }

    private static function escape(string $value): string
    {
        return str_replace(["\\", "\n", '"'], ["\\\\", "\\n", "\\\""], $value);
    }

    private static function float(float $value): string
    {
        return rtrim(rtrim(sprintf('%.9F', $value), '0'), '.') ?: '0';
    }
}
