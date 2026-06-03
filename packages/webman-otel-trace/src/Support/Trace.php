<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Support;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use support\Context as WebmanContext;
use support\Log;
use Throwable;

final class Trace
{
    private const SPAN_STACK_KEY = 'openb8.webman_otel_trace.business_span_stack';

    public static function span(
        string $name,
        callable $callback,
        array $attributes = [],
        int $kind = SpanKind::KIND_INTERNAL,
        ?string $tracerName = null
    ): mixed {
        if (!self::isBusinessSpanEnabled()) {
            return self::call($callback, Span::getCurrent());
        }

        $parentContext = Span::getCurrent()->getContext();
        $attributes = self::normalizeAttributes($attributes, true);
        $span = Globals::tracerProvider()
            ->getTracer($tracerName ?: self::tracerName(), Version::VERSION)
            ->spanBuilder($name)
            ->setSpanKind($kind)
            ->setAttributes($attributes)
            ->startSpan();

        $scope = $span->activate();
        $startedAt = microtime(true);
        $status = StatusCode::STATUS_OK;
        $statusDescription = '';
        $exceptionPayload = null;

        self::pushLocalRecord([
            'time' => date('Y-m-d H:i:s', (int)$startedAt),
            'timestamp' => $startedAt,
            'trace_id' => $span->getContext()->getTraceId(),
            'span_id' => $span->getContext()->getSpanId(),
            'parent_span_id' => $parentContext->isValid() ? $parentContext->getSpanId() : '',
            'name' => $name,
            'kind' => self::spanKindName($kind),
            'attributes' => $attributes,
            'events' => [],
        ]);

        try {
            return self::call($callback, $span);
        } catch (Throwable $throwable) {
            $status = StatusCode::STATUS_ERROR;
            $statusDescription = $throwable->getMessage();
            $exceptionPayload = [
                'class' => $throwable::class,
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile() . ':' . $throwable->getLine(),
            ];
            $span->recordException($throwable);
            $span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());
            throw $throwable;
        } finally {
            $durationMs = round((microtime(true) - $startedAt) * 1000, 3);
            $span->setAttribute('app.business.duration_ms', $durationMs);
            self::finishLocalRecord($span, $durationMs, $status, $statusDescription, $exceptionPayload);
            $span->end();
            $scope->detach();
        }
    }

    public static function setAttribute(string $key, bool|int|float|string|array|null $value): void
    {
        if (!self::isBusinessSpanEnabled()) {
            return;
        }

        $normalized = self::normalizeAttributeValue($value);
        Span::getCurrent()->setAttribute($key, $normalized);
        self::mergeLocalAttributes([$key => $normalized]);
    }

    public static function setAttributes(array $attributes): void
    {
        if (!self::isBusinessSpanEnabled()) {
            return;
        }

        $normalized = self::normalizeAttributes($attributes);
        Span::getCurrent()->setAttributes($normalized);
        self::mergeLocalAttributes($normalized);
    }

    public static function addEvent(string $name, array $attributes = []): void
    {
        if (!self::isBusinessSpanEnabled()) {
            return;
        }

        $normalized = self::normalizeAttributes($attributes);
        Span::getCurrent()->addEvent($name, $normalized);
        self::appendLocalEvent($name, $normalized);
    }

    public static function context(): array
    {
        return TraceContext::current();
    }

    private static function isBusinessSpanEnabled(): bool
    {
        return (bool)config('plugin.openb8.webman-otel-trace.app.enable', true)
            && (bool)config('plugin.openb8.webman-otel-trace.app.business_span.enable', true);
    }

    private static function tracerName(): string
    {
        return (string)config(
            'plugin.openb8.webman-otel-trace.app.business_span.tracer_name',
            'openb8.webman.business'
        );
    }

    private static function call(callable $callback, SpanInterface $span): mixed
    {
        try {
            $reflection = new \ReflectionFunction(\Closure::fromCallable($callback));
            if ($reflection->getNumberOfParameters() > 0) {
                return $callback($span);
            }
        } catch (\ReflectionException) {
        }

        return $callback();
    }

    /** @param array<string, mixed> $record */
    private static function pushLocalRecord(array $record): void
    {
        $stack = self::spanStack();
        $stack[] = $record;
        self::setSpanStack($stack);
    }

    /** @param array<string, bool|int|float|string|array|null> $attributes */
    private static function mergeLocalAttributes(array $attributes): void
    {
        $stack = self::spanStack();
        if ($stack === []) {
            return;
        }

        $index = count($stack) - 1;
        $stack[$index]['attributes'] = array_merge((array)($stack[$index]['attributes'] ?? []), $attributes);
        self::setSpanStack($stack);
    }

    /** @param array<string, bool|int|float|string|array|null> $attributes */
    private static function appendLocalEvent(string $name, array $attributes): void
    {
        $stack = self::spanStack();
        if ($stack === []) {
            return;
        }

        $index = count($stack) - 1;
        $events = (array)($stack[$index]['events'] ?? []);
        $events[] = [
            'time' => date('Y-m-d H:i:s'),
            'timestamp' => microtime(true),
            'name' => $name,
            'attributes' => $attributes,
        ];
        $stack[$index]['events'] = $events;
        self::setSpanStack($stack);
    }

    /** @return array<int, array<string, mixed>> */
    private static function spanStack(): array
    {
        $stack = WebmanContext::get(self::SPAN_STACK_KEY, []);

        return is_array($stack) ? $stack : [];
    }

    /** @param array<int, array<string, mixed>> $stack */
    private static function setSpanStack(array $stack): void
    {
        WebmanContext::set(self::SPAN_STACK_KEY, $stack);
    }

    /** @param array<string, string>|null $exception */
    private static function finishLocalRecord(
        SpanInterface $span,
        float $durationMs,
        string $status,
        string $statusDescription,
        ?array $exception
    ): void {
        $spanId = $span->getContext()->getSpanId();
        $stack = self::spanStack();
        $record = null;

        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if (($stack[$i]['span_id'] ?? '') === $spanId) {
                $record = $stack[$i];
                array_splice($stack, $i, 1);
                break;
            }
        }

        self::setSpanStack($stack);

        if ($record === null) {
            $record = [
                'time' => date('Y-m-d H:i:s'),
                'timestamp' => microtime(true),
                'trace_id' => $span->getContext()->getTraceId(),
                'span_id' => $spanId,
                'parent_span_id' => '',
                'name' => 'business.span',
                'kind' => self::spanKindName(SpanKind::KIND_INTERNAL),
                'attributes' => [],
                'events' => [],
            ];
        }

        $record['ended_at'] = date('Y-m-d H:i:s');
        $record['duration_ms'] = $durationMs;
        $record['status'] = $status;
        $record['status_description'] = $statusDescription;
        if ($exception !== null) {
            $record['exception'] = $exception;
        }

        self::writeLocalRecord($record);
    }

    /** @param array<string, mixed> $record */
    private static function writeLocalRecord(array $record): void
    {
        $config = (array)config('plugin.openb8.webman-otel-trace.app.business_span', []);
        $console = (bool)($config['console'] ?? false);
        $file = (bool)($config['file'] ?? true);
        if (!$console && !$file) {
            return;
        }

        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $line = json_encode(['message' => 'business span log encode failed'], JSON_UNESCAPED_UNICODE);
        }

        if ($console) {
            echo '[otel-span] ' . $line . PHP_EOL;
        }

        if ($file) {
            try {
                Log::channel('plugin.openb8.webman-otel-trace.span')->info($line);
            } catch (Throwable $logError) {
                echo '[otel-span] 写入日志失败: ' . $logError->getMessage() . PHP_EOL;
            }
        }
    }

    private static function spanKindName(int $kind): string
    {
        return match ($kind) {
            SpanKind::KIND_SERVER => 'server',
            SpanKind::KIND_CLIENT => 'client',
            SpanKind::KIND_PRODUCER => 'producer',
            SpanKind::KIND_CONSUMER => 'consumer',
            default => 'internal',
        };
    }

    private static function normalizeAttributes(array $attributes, bool $includeBusinessType = false): array
    {
        $normalized = $includeBusinessType ? ['app.span.type' => 'business'] : [];

        foreach ($attributes as $key => $value) {
            if (is_string($key) && $key !== '') {
                $normalized[$key] = self::normalizeAttributeValue($value);
            }
        }

        return $normalized;
    }

    private static function normalizeAttributeValue(mixed $value): bool|int|float|string|array|null
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            if (!array_is_list($value)) {
                return self::stringify($value);
            }

            return array_map(static fn (mixed $item): bool|int|float|string|null => match (true) {
                $item === null => null,
                is_bool($item), is_int($item), is_float($item), is_string($item) => $item,
                default => self::stringify($item),
            }, $value);
        }

        return self::stringify($value);
    }

    private static function stringify(mixed $value): string
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded) && $encoded !== '') {
            return $encoded;
        }

        return get_debug_type($value);
    }
}
