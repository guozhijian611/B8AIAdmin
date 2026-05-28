---
name: workbunny-rabbitmq
description: B8AIadmin 项目中使用 workbunny/webman-rabbitmq 3.x 开发和排查 RabbitMQ/AMQP 队列的技能。Use when Codex needs to add or debug RabbitMQ producers, consumers, queue Builders, delayed queues, Workbunny process files under process/workbunny/rabbitmq, or config/plugin/workbunny/webman-rabbitmq settings in this Webman/SaiAdmin project.
---

# Workbunny RabbitMQ

## Overview

Use this skill for RabbitMQ queue work in B8AIadmin. It captures the local Webman/SaiAdmin layout and the `workbunny/webman-rabbitmq` 3.x workflow so another Codex instance can add producers, consumers, delayed queues, or diagnose queue process/config issues without rediscovering the plugin.

Primary sources: Workerman plugin 67 (`workbunny/webman-rabbitmq`), local `server/composer.json`, local generated plugin config, and installed vendor code.

## Project Facts

- Backend root: `server/`.
- Dependency is already present in `server/composer.json`: `workbunny/webman-rabbitmq` `^3.0`.
- Webman dependency is `workerman/webman-framework` `^2.1`; plugin 3.x requires PHP >= 8.1, webman-framework >= 2.0 or workerman >= 5.1, RabbitMQ >= 3.10, and one coroutine/event-loop environment from `revolt/event-loop`, `swow`, or `swoole`.
- Plugin config lives at `server/config/plugin/workbunny/webman-rabbitmq/`.
- Current default connection file is `server/config/plugin/workbunny/webman-rabbitmq/connections.php`; it currently points to `127.0.0.1:5672`, vhost `/`, username `admin`, password `admin`.
- Builder classes are generated under `server/process/workbunny/rabbitmq/`.
- Builder process registrations are written to `server/config/plugin/workbunny/webman-rabbitmq/process.php`.
- Commands are registered in `server/config/plugin/workbunny/webman-rabbitmq/command.php`.

## Operating Rules

- Always inspect `git status --short` before editing; do not overwrite unrelated user changes, especially existing composer or generated plugin-config changes.
- Keep queue changes in backend files under `server/`. Do not involve the frontend unless the user asks for UI around queue state.
- Do not commit real RabbitMQ production credentials. If credentials must change, prefer project-supported environment config and ask when the target environment is unclear.
- After any functional code/config change, run focused backend validation and create a Chinese git commit that follows conventional commit style, unless the user explicitly asks not to commit.

## Command Workflow

Run Workbunny commands from `server/`.

```bash
cd server
php webman workbunny:rabbitmq-builder -h
php webman workbunny:rabbitmq-list
php webman workbunny:rabbitmq-remove -h
```

Create a normal queue Builder:

```bash
cd server
php webman workbunny:rabbitmq-builder order/paid 1
```

This creates `server/process/workbunny/rabbitmq/order/PaidBuilder.php` and appends a process entry like `process.workbunny.rabbitmq.order.PaidBuilder`.

Useful options:

- `-c, --connection=CONNECTION`: use a named connection from `connections.php`; default is `default`.
- `-d, --delayed`: create a delayed queue Builder; class name receives a `Delayed` suffix.
- `-m, --mode=MODE`: Builder mode; built-in mode is `queue`.
- `<count>`: number of Webman worker processes for that Builder; default is `1`.

After generation, inspect both the Builder and `process.php`; the generated `handler()` is only a placeholder.

## Consumer Builders

Generated Builders extend `Workbunny\WebmanRabbitMQ\Builders\QueueBuilder`.

Key fields:

- `protected ?string $connection = 'default';`
- `protected array $queueConfig`: queue name, delayed flag, prefetch/QOS settings, routing key.
- `protected string $exchangeType`: use constants such as `Constants::DIRECT`.
- `protected ?string $exchangeName`: exchange name; default generation uses the full process class path.

Handler contract:

```php
public function handler(BunnyMessage $message, Channel $channel, ConnectionInterface $connection): string
{
    // Decode and validate $message->content here.
    // Use idempotency keys for database side effects.
    return Constants::ACK;
}
```

Return one of:

- `Constants::ACK`: processed successfully.
- `Constants::NACK` or `Constants::REJECT`: reject a message when it should not be retried.
- `Constants::REQUEUE`: publish the message back with retry headers such as `workbunny-requeue-count`.

Consumer guidance:

- Make handlers idempotent. Queue messages can be consumed more than once.
- Catch known permanent business errors and avoid infinite `REQUEUE`.
- For slow jobs, prefer a small `prefetch_count` such as `1`.
- For high concurrency, consider lowering connection/channel pool `wait_timeout`; plugin docs note this avoids long waits under shadow-mode compensation.
- Webman is long-running. Restart or reload the server after changing process/config files before runtime verification.

## Publishing Messages

For normal publish code, use the plugin helper:

```php
use function Workbunny\WebmanRabbitMQ\publish;

$payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
publish(new \process\workbunny\rabbitmq\order\PaidBuilder(), $payload);
```

When using routing keys or headers:

```php
publish(
    new \process\workbunny\rabbitmq\order\PaidBuilder(),
    $payload,
    routingKey: 'order.paid',
    headers: ['trace_id' => $traceId]
);
```

For custom exchange/queue/routing behavior, prefer a dedicated Builder. Use raw `BuilderConfig` and `ConnectionsManagement::connection()` only when the Builder abstraction is too limiting.

## Delayed Queues

Delayed queues require the RabbitMQ `rabbitmq_delayed_message_exchange` plugin on the RabbitMQ server.

Create a delayed Builder:

```bash
cd server
php webman workbunny:rabbitmq-builder order/timeout 1 --delayed
```

Publish with an `x-delay` header in milliseconds:

```php
publish(new \process\workbunny\rabbitmq\order\TimeoutBuilderDelayed(), $payload, headers: [
    'x-delay' => 10000,
]);
```

Important plugin behavior:

- Sending `x-delay` to a normal Builder is invalid.
- Sending no `x-delay` to a delayed Builder is invalid.
- If the deployment cannot install `rabbitmq_delayed_message_exchange`, use priority queues plus `Constants::REQUEUE` and a timestamp header as a fallback pattern.

## Validation

Use focused validation after queue edits:

```bash
cd server
php -l process/workbunny/rabbitmq/order/PaidBuilder.php
php -l config/plugin/workbunny/webman-rabbitmq/process.php
php webman workbunny:rabbitmq-list
```

For runtime checks, verify the local RabbitMQ service is reachable before starting consumers. If the Webman service is already running, restart or reload using the project’s existing `webman` script, then watch `server/runtime/logs/` for consumer errors.
