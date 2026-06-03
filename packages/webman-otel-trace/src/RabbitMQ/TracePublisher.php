<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\RabbitMQ;

use OpenB8\WebmanOtelTrace\Metrics\MetricRegistry;
use OpenB8\WebmanOtelTrace\Support\Version;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use Throwable;
use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;

use function Workbunny\WebmanRabbitMQ\publish as rabbitmq_publish;

class TracePublisher
{
    public static function publish(AbstractBuilder $builder, string $body, ?string $routingKey = null, ?array $headers = null): ?int
    {
        if (!config('plugin.openb8.webman-otel-trace.app.enable', true)
            || !config('plugin.openb8.webman-otel-trace.app.rabbitmq.enable', true)
        ) {
            return rabbitmq_publish($builder, $body, $routingKey, $headers);
        }

        $config = $builder->getBuilderConfig();
        $destination = $config->getExchange() ?: $config->getQueue() ?: $builder->getBuilderName();
        $span = Globals::tracerProvider()
            ->getTracer('openb8.webman.rabbitmq', Version::VERSION)
            ->spanBuilder('rabbitmq publish ' . $destination)
            ->setSpanKind(SpanKind::KIND_PRODUCER)
            ->setAttributes([
                'messaging.system' => 'rabbitmq',
                'messaging.operation.name' => 'publish',
                'messaging.destination.name' => $destination,
                'messaging.rabbitmq.routing_key' => $routingKey ?? $config->getRoutingKey(),
            ])
            ->startSpan();

        $scope = $span->activate();
        $headers = $headers ?? $config->getHeaders();

        try {
            Globals::propagator()->inject($headers, ArrayAccessGetterSetter::getInstance(), Context::getCurrent());
            $result = rabbitmq_publish($builder, $body, $routingKey, $headers);
            MetricRegistry::recordRabbitmq('publish', $destination, 'ok');
            return $result;
        } catch (Throwable $throwable) {
            $span->recordException($throwable);
            $span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());
            MetricRegistry::recordRabbitmq('publish', $destination, 'error');
            throw $throwable;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}
