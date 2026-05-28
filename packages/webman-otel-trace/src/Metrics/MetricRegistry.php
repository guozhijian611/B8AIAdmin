<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Metrics;

use Throwable;

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
        if (!self::enabled()) {
            return;
        }

        $labels = [
            'method' => strtoupper($method),
            'path' => self::normalizePath($path),
            'status' => (string)$status,
        ];
        $key = self::key($labels);

        self::incrementMemoryCounter(self::$httpRequests, $key, $labels);
        self::incrementMemorySummary(self::$httpDurations, $key, $labels, max(0.0, $durationSeconds));
        self::updateFileMetrics(static function (array &$metrics) use ($key, $labels, $durationSeconds): void {
            self::incrementCounter($metrics, 'http_requests', $key, $labels);
            self::incrementSummary($metrics, 'http_durations', $key, $labels, max(0.0, $durationSeconds));
        });
    }

    public static function recordRabbitmq(string $operation, string $destination, string $status): void
    {
        if (!self::enabled()) {
            return;
        }

        $labels = [
            'operation' => $operation,
            'destination' => $destination,
            'status' => $status,
        ];
        $key = self::key($labels);

        self::incrementMemoryCounter(self::$rabbitmqMessages, $key, $labels);
        self::updateFileMetrics(static function (array &$metrics) use ($key, $labels): void {
            self::incrementCounter($metrics, 'rabbitmq_messages', $key, $labels);
        });
    }

    public static function render(): string
    {
        $snapshot = self::useFileStorage() ? self::readFileMetrics() : null;
        $httpRequests = $snapshot['http_requests'] ?? self::$httpRequests;
        $httpDurations = $snapshot['http_durations'] ?? self::$httpDurations;
        $rabbitmqMessages = $snapshot['rabbitmq_messages'] ?? self::$rabbitmqMessages;

        $lines = [
            '# HELP webman_http_requests_total Total HTTP requests observed by webman-otel-trace.',
            '# TYPE webman_http_requests_total counter',
        ];

        foreach ($httpRequests as $metric) {
            $lines[] = 'webman_http_requests_total' . self::labels($metric['labels']) . ' ' . $metric['value'];
        }

        $lines[] = '# HELP webman_http_request_duration_seconds HTTP request duration in seconds.';
        $lines[] = '# TYPE webman_http_request_duration_seconds summary';
        foreach ($httpDurations as $metric) {
            $lines[] = 'webman_http_request_duration_seconds_count' . self::labels($metric['labels']) . ' ' . $metric['count'];
            $lines[] = 'webman_http_request_duration_seconds_sum' . self::labels($metric['labels']) . ' ' . self::float($metric['sum']);
        }

        $lines[] = '# HELP webman_rabbitmq_messages_total RabbitMQ publish/consume operations observed by webman-otel-trace.';
        $lines[] = '# TYPE webman_rabbitmq_messages_total counter';
        foreach ($rabbitmqMessages as $metric) {
            $lines[] = 'webman_rabbitmq_messages_total' . self::labels($metric['labels']) . ' ' . $metric['value'];
        }

        return implode("\n", $lines) . "\n";
    }

    private static function enabled(): bool
    {
        return (bool)self::config('plugin.openb8.webman-otel-trace.app.metrics.enable', true);
    }

    private static function useFileStorage(): bool
    {
        return strtolower((string)self::config('plugin.openb8.webman-otel-trace.app.metrics.storage', 'memory')) === 'file';
    }

    private static function metricsFile(): string
    {
        $file = (string)self::config('plugin.openb8.webman-otel-trace.app.metrics.file', '');
        if ($file !== '') {
            return $file;
        }

        if (function_exists('runtime_path')) {
            return runtime_path() . '/otel-trace/metrics.json';
        }

        return sys_get_temp_dir() . '/webman-otel-trace-metrics.json';
    }

    /** @param callable $callback */
    private static function updateFileMetrics(callable $callback): void
    {
        if (!self::useFileStorage()) {
            return;
        }

        $file = self::metricsFile();
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            return;
        }

        $handle = @fopen($file, 'c+');
        if (!$handle) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            $metrics = self::readMetricsFromHandle($handle);
            $callback($metrics);
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($metrics, JSON_UNESCAPED_SLASHES) ?: '{}');
            fflush($handle);
            flock($handle, LOCK_UN);
        } catch (Throwable) {
            @flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /** @return array<string, mixed>|null */
    private static function readFileMetrics(): ?array
    {
        $file = self::metricsFile();
        if (!is_file($file)) {
            return null;
        }

        $handle = @fopen($file, 'r');
        if (!$handle) {
            return null;
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return null;
            }
            $metrics = self::readMetricsFromHandle($handle);
            flock($handle, LOCK_UN);
        } catch (Throwable) {
            @flock($handle, LOCK_UN);
            $metrics = null;
        } finally {
            fclose($handle);
        }

        return $metrics ?: null;
    }

    /** @param resource $handle @return array<string, mixed> */
    private static function readMetricsFromHandle($handle): array
    {
        rewind($handle);
        $contents = stream_get_contents($handle);
        $metrics = is_string($contents) && $contents !== '' ? json_decode($contents, true) : null;
        if (!is_array($metrics)) {
            $metrics = [];
        }

        $metrics['http_requests'] ??= [];
        $metrics['http_durations'] ??= [];
        $metrics['rabbitmq_messages'] ??= [];

        return $metrics;
    }

    /** @param array<string, array{labels: array<string, string>, value: int}> $bucket */
    private static function incrementMemoryCounter(array &$bucket, string $key, array $labels): void
    {
        $bucket[$key] ??= ['labels' => $labels, 'value' => 0];
        $bucket[$key]['value']++;
    }

    /** @param array<string, array{labels: array<string, string>, count: int, sum: float}> $bucket */
    private static function incrementMemorySummary(array &$bucket, string $key, array $labels, float $durationSeconds): void
    {
        $bucket[$key] ??= ['labels' => $labels, 'count' => 0, 'sum' => 0.0];
        $bucket[$key]['count']++;
        $bucket[$key]['sum'] += $durationSeconds;
    }

    /** @param array<string, mixed> $metrics */
    private static function incrementCounter(array &$metrics, string $bucket, string $key, array $labels): void
    {
        $metrics[$bucket][$key] ??= ['labels' => $labels, 'value' => 0];
        $metrics[$bucket][$key]['value']++;
    }

    /** @param array<string, mixed> $metrics */
    private static function incrementSummary(array &$metrics, string $bucket, string $key, array $labels, float $durationSeconds): void
    {
        $metrics[$bucket][$key] ??= ['labels' => $labels, 'count' => 0, 'sum' => 0.0];
        $metrics[$bucket][$key]['count']++;
        $metrics[$bucket][$key]['sum'] += $durationSeconds;
    }

    private static function config(string $key, mixed $default): mixed
    {
        return function_exists('config') ? config($key, $default) : $default;
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
