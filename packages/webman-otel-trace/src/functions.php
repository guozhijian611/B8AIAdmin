<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\RabbitMQ;

use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;

function publish(AbstractBuilder $builder, string $body, ?string $routingKey = null, ?array $headers = null): ?int
{
    return TracePublisher::publish($builder, $body, $routingKey, $headers);
}

namespace OpenB8\WebmanOtelTrace;

function span(string $name, callable $callback, array $attributes = []): mixed
{
    return Support\Trace::span($name, $callback, $attributes);
}
