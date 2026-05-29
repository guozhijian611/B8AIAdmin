<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Middleware;

use OpenB8\WebmanOtelTrace\Support\TraceContext;
use support\Log;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class RequestLogMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $config = $this->config();
        if (!$config['enable'] || $this->shouldIgnore($request, $config['ignore_paths'])) {
            return $handler($request);
        }

        $startedAt = microtime(true);
        $response = null;
        $throwable = null;

        try {
            $response = $handler($request);
            return $response;
        } catch (Throwable $caught) {
            $throwable = $caught;
            throw $caught;
        } finally {
            $this->write($request, $response, $throwable, microtime(true) - $startedAt, $config);
        }
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        $config = (array)config('plugin.openb8.webman-otel-trace.app.request_log', []);

        return [
            'enable' => (bool)($config['enable'] ?? true),
            'console' => (bool)($config['console'] ?? false),
            'file' => (bool)($config['file'] ?? true),
            'include_headers' => (bool)($config['include_headers'] ?? false),
            'include_request_body' => (bool)($config['include_request_body'] ?? true),
            'include_response_body' => (bool)($config['include_response_body'] ?? true),
            'max_body_length' => max(256, (int)($config['max_body_length'] ?? 4096)),
            'ignore_paths' => (array)($config['ignore_paths'] ?? ['/metrics']),
            'mask_sensitive' => (bool)($config['mask_sensitive'] ?? true),
            'sensitive_fields' => array_map('strtolower', (array)($config['sensitive_fields'] ?? [])),
        ];
    }

    /** @param array<int, string> $ignorePaths */
    private function shouldIgnore(Request $request, array $ignorePaths): bool
    {
        $path = '/' . ltrim($request->path(), '/');
        $url = $request->url();

        foreach ($ignorePaths as $ignorePath) {
            $ignorePath = '/' . ltrim((string)$ignorePath, '/');
            if ($ignorePath === '/') {
                continue;
            }
            if ($path === $ignorePath || str_starts_with($path, rtrim($ignorePath, '/') . '/')) {
                return true;
            }
            if ($url !== '' && str_contains($url, $ignorePath)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $config */
    private function write(Request $request, ?Response $response, ?Throwable $throwable, float $duration, array $config): void
    {
        if (!$config['console'] && !$config['file']) {
            return;
        }

        $trace = TraceContext::current();
        $status = $response?->getStatusCode() ?? 500;
        $payload = [
            'time' => date('Y-m-d H:i:s'),
            'trace_id' => $trace['trace_id'],
            'span_id' => $trace['span_id'],
            'method' => $request->method(),
            'path' => '/' . ltrim($request->path(), '/'),
            'url' => $request->url(),
            'status_code' => $status,
            'business_code' => $this->businessCode($response, $config['max_body_length']),
            'duration_ms' => round($duration * 1000, 3),
            'client_ip' => $request->getRealIp(false),
        ];

        if ($config['include_headers']) {
            $payload['headers'] = $this->sanitize($request->header() ?: [], $config['sensitive_fields'], $config['mask_sensitive']);
        }

        if ($config['include_request_body']) {
            $payload['request'] = $this->truncate(
                $this->sanitize($request->all(), $config['sensitive_fields'], $config['mask_sensitive']),
                $config['max_body_length']
            );
        }

        if ($response && $config['include_response_body']) {
            $payload['response'] = $this->truncate(
                $this->sanitize($this->decodeBody($response->rawBody()), $config['sensitive_fields'], $config['mask_sensitive']),
                $config['max_body_length']
            );
        }

        if ($throwable) {
            $payload['exception'] = [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile() . ':' . $throwable->getLine(),
            ];
        }

        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $line = json_encode(['message' => 'request log encode failed'], JSON_UNESCAPED_UNICODE);
        }

        if ($config['console']) {
            echo '[otel-request] ' . $line . PHP_EOL;
        }

        if ($config['file']) {
            try {
                Log::channel('plugin.openb8.webman-otel-trace.request')->info($line);
            } catch (Throwable $logError) {
                echo '[otel-request] 写入日志失败: ' . $logError->getMessage() . PHP_EOL;
            }
        }
    }

    private function businessCode(?Response $response, int $maxLength): int|string|null
    {
        if (!$response) {
            return null;
        }

        $body = $response->rawBody();
        if ($body === '' || strlen($body) > $maxLength) {
            return null;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !array_key_exists('code', $decoded)) {
            return null;
        }

        return is_int($decoded['code']) || is_string($decoded['code']) ? $decoded['code'] : null;
    }

    private function decodeBody(string $body): mixed
    {
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
    }

    /** @param array<int, string> $sensitiveFields */
    private function sanitize(mixed $value, array $sensitiveFields, bool $maskSensitive): mixed
    {
        if (!is_array($value)) {
            return is_string($value) ? $this->normalizeString($value) : $value;
        }

        $sanitized = [];
        foreach ($value as $key => $item) {
            $keyString = strtolower((string)$key);
            if ($maskSensitive && $this->isSensitiveKey($keyString, $sensitiveFields)) {
                $sanitized[$key] = '******';
                continue;
            }
            $sanitized[$key] = $this->sanitize($item, $sensitiveFields, $maskSensitive);
        }

        return $sanitized;
    }

    /** @param array<int, string> $sensitiveFields */
    private function isSensitiveKey(string $key, array $sensitiveFields): bool
    {
        foreach ($sensitiveFields as $field) {
            if ($field !== '' && str_contains($key, $field)) {
                return true;
            }
        }

        return false;
    }

    private function truncate(mixed $value, int $maxLength): mixed
    {
        if (is_string($value)) {
            $value = $this->normalizeString($value);
        }

        $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return $value;
        }

        if (strlen($json) <= $maxLength) {
            return $value;
        }

        return substr($json, 0, $maxLength) . '...[truncated]';
    }

    private function normalizeString(string $value): string
    {
        return str_replace(["\r\n", "\r", "\n"], ['\n', '\n', '\n'], $value);
    }
}
