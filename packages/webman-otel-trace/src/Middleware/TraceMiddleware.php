<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Middleware;

use OpenB8\WebmanOtelTrace\Metrics\MetricRegistry;
use OpenB8\WebmanOtelTrace\Support\TraceContext;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class TraceMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if (!config('plugin.openb8.webman-otel-trace.app.enable', true)) {
            return $handler($request);
        }

        $startedAt = microtime(true);
        $method = $request->method();
        $path = $request->path();
        $status = 500;
        $response = null;
        $spanName = $method . ' ' . $path;
        $parent = Globals::propagator()->extract(
            $request->header() ?: [],
            ArrayAccessGetterSetter::getInstance(),
            Context::getCurrent()
        );

        $span = Globals::tracerProvider()
            ->getTracer('openb8.webman.http', '0.1.0')
            ->spanBuilder($spanName)
            ->setParent($parent)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttributes($this->requestAttributes($request))
            ->startSpan();

        $scope = $span->storeInContext($parent)->activate();

        try {
            $response = $handler($request);
            $status = $response->getStatusCode();
            $span->setAttribute('http.response.status_code', $status);
            if ($status >= 500) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            }
            $this->injectResponseTraceHeaders($response);
            return $response;
        } catch (Throwable $throwable) {
            $span->recordException($throwable);
            $span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());
            throw $throwable;
        } finally {
            $duration = microtime(true) - $startedAt;
            $span->setAttribute('webman.duration_ms', round($duration * 1000, 3));
            $span->end();
            $scope->detach();

            MetricRegistry::recordHttp($method, $path, $status, $duration);
        }
    }

    /** @return array<string, bool|int|float|string|array|null> */
    private function requestAttributes(Request $request): array
    {
        $attributes = [
            'http.request.method' => $request->method(),
            'url.path' => $request->path(),
            'url.query' => $request->queryString(),
            'server.address' => (string)$request->host(true),
            'server.port' => $request->getLocalPort(),
            'client.address' => $request->getRealIp(false),
            'user_agent.original' => (string)$request->header('user-agent', ''),
        ];

        foreach ((array)config('plugin.openb8.webman-otel-trace.app.trace.capture_request_headers', []) as $header) {
            $header = strtolower((string)$header);
            $value = $request->header($header);
            if ($value !== null && $value !== '') {
                $attributes['http.request.header.' . str_replace('-', '_', $header)] = is_array($value) ? $value : (string)$value;
            }
        }

        return $attributes;
    }

    private function injectResponseTraceHeaders(Response $response): void
    {
        if (!config('plugin.openb8.webman-otel-trace.app.trace.response_trace_header', true)) {
            return;
        }

        $carrier = [];
        Globals::propagator()->inject($carrier, ArrayAccessGetterSetter::getInstance(), Context::getCurrent());
        foreach ($carrier as $name => $value) {
            if (is_string($value)) {
                $response->withHeader($name, $value);
            }
        }

        $context = TraceContext::current();
        if ($context['trace_id']) {
            $response->withHeader('x-trace-id', $context['trace_id']);
        }
    }
}
