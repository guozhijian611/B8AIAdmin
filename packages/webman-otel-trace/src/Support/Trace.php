<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Support;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

final class Trace
{
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

        $span = Globals::tracerProvider()
            ->getTracer($tracerName ?: self::tracerName(), Version::VERSION)
            ->spanBuilder($name)
            ->setSpanKind($kind)
            ->setAttributes(self::normalizeAttributes($attributes, true))
            ->startSpan();

        $scope = $span->activate();
        $startedAt = microtime(true);

        try {
            return self::call($callback, $span);
        } catch (Throwable $throwable) {
            $span->recordException($throwable);
            $span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());
            throw $throwable;
        } finally {
            $span->setAttribute('app.business.duration_ms', round((microtime(true) - $startedAt) * 1000, 3));
            $span->end();
            $scope->detach();
        }
    }

    public static function setAttribute(string $key, bool|int|float|string|array|null $value): void
    {
        if (!self::isBusinessSpanEnabled()) {
            return;
        }

        Span::getCurrent()->setAttribute($key, self::normalizeAttributeValue($value));
    }

    public static function setAttributes(array $attributes): void
    {
        if (!self::isBusinessSpanEnabled()) {
            return;
        }

        Span::getCurrent()->setAttributes(self::normalizeAttributes($attributes));
    }

    public static function addEvent(string $name, array $attributes = []): void
    {
        if (!self::isBusinessSpanEnabled()) {
            return;
        }

        Span::getCurrent()->addEvent($name, self::normalizeAttributes($attributes));
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
