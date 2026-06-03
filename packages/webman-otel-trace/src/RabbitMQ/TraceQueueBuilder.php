<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\RabbitMQ;

use Bunny\Message as BunnyMessage;
use OpenB8\WebmanOtelTrace\Metrics\MetricRegistry;
use OpenB8\WebmanOtelTrace\Support\Version;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use Throwable;
use Workbunny\WebmanRabbitMQ\Builders\QueueBuilder;
use Workbunny\WebmanRabbitMQ\Connection\Channel;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Constants;

abstract class TraceQueueBuilder extends QueueBuilder
{
    final public function handler(BunnyMessage $message, Channel $channel, ConnectionInterface $connection): string
    {
        if (!config('plugin.openb8.webman-otel-trace.app.enable', true)
            || !config('plugin.openb8.webman-otel-trace.app.rabbitmq.enable', true)
        ) {
            return $this->handle($message, $channel, $connection);
        }

        $destination = $this->getBuilderConfig()->getQueue() ?: $this->getBuilderName();
        $parent = Globals::propagator()->extract(
            $message->headers ?? [],
            ArrayAccessGetterSetter::getInstance(),
            Context::getCurrent()
        );

        $span = Globals::tracerProvider()
            ->getTracer('openb8.webman.rabbitmq', Version::VERSION)
            ->spanBuilder('rabbitmq consume ' . $destination)
            ->setParent($parent)
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->setAttributes([
                'messaging.system' => 'rabbitmq',
                'messaging.operation.name' => 'consume',
                'messaging.destination.name' => $destination,
                'messaging.message.id' => (string)($message->headers['message_id'] ?? ''),
            ])
            ->startSpan();

        $scope = $span->storeInContext($parent)->activate();

        try {
            $tag = $this->handle($message, $channel, $connection);
            MetricRegistry::recordRabbitmq('consume', $destination, $tag);
            if (in_array($tag, [Constants::NACK, Constants::REQUEUE, Constants::REJECT], true)) {
                $span->setStatus(StatusCode::STATUS_ERROR, $tag);
            }
            return $tag;
        } catch (Throwable $throwable) {
            $span->recordException($throwable);
            $span->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());
            MetricRegistry::recordRabbitmq('consume', $destination, 'error');
            throw $throwable;
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    abstract protected function handle(BunnyMessage $message, Channel $channel, ConnectionInterface $connection): string;
}
