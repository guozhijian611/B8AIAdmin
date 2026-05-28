<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\RabbitMQ;

use Workbunny\WebmanRabbitMQ\Builders\AbstractBuilder;

function publish(AbstractBuilder $builder, string $body, ?string $routingKey = null, ?array $headers = null): ?int
{
    return TracePublisher::publish($builder, $body, $routingKey, $headers);
}
