# openb8/webman-otel-trace

Webman OpenTelemetry trace 插件，当前项目建议用它统一 HTTP、PDO/ThinkORM、RabbitMQ、日志 trace id 和 Prometheus 文本指标。

## 使用方式

```bash
cd server
composer require openb8/webman-otel-trace:dev-main
```

Composer 会通过 Webman 的 `support\Plugin::install` 事件复制 `config/plugin/openb8/webman-otel-trace` 配置目录。

HTTP trace 会通过 Webman 全局中间件 `@` 自动创建 server span，主应用和 SaiAdmin 等 plugin 路由都会覆盖，并从 `traceparent`、`tracestate`、`baggage` 提取上游上下文。

ThinkORM 底层走 PDO 时，由 `open-telemetry/opentelemetry-auto-pdo` 负责自动埋点，前提是 PHP 安装并启用了 `ext-opentelemetry`。

RabbitMQ 生产端使用：

```php
use function OpenB8\WebmanOtelTrace\RabbitMQ\publish;

publish($builder, json_encode($payload, JSON_UNESCAPED_UNICODE));
```

RabbitMQ 消费端继承 `TraceQueueBuilder`，把业务逻辑写到 `handle()`：

```php
use Bunny\Message as BunnyMessage;
use OpenB8\WebmanOtelTrace\RabbitMQ\TraceQueueBuilder;
use Workbunny\WebmanRabbitMQ\Connection\Channel;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Constants;

class DemoQueue extends TraceQueueBuilder
{
    protected function handle(BunnyMessage $message, Channel $channel, ConnectionInterface $connection): string
    {
        return Constants::ACK;
    }
}
```

本地调试默认把 span 输出到 stdout。生产建议配置：

```php
'exporter' => [
    'driver' => 'otlp',
    'endpoint' => 'http://otel-collector:4318',
    'protocol' => 'http/protobuf',
],
```

不想在控制台输出 trace 时，可以把 `exporter.driver` 设置为 `otlp` 发到 Collector，或者设置为 `none` 暂时关闭 trace 导出。`thinkorm-log` 自己的 SQL/API 控制台输出不受这个配置控制，需要在 `config/plugin/guozhijian611/thinkorm-log/app.php` 里分别关闭 `console`。

`/metrics` 暴露 Prometheus 文本指标；日志会通过 Monolog processor 自动带上 `trace_id` 和 `span_id`。

当前项目未启用 `ext-ffi`，插件默认会使用进程级 OpenTelemetry `ContextStorage`，避免 Webman fiber 下 PDO 自动埋点出现 `Access to not initialized OpenTelemetry context in fiber` 告警。若以后启用 `ext-ffi` 并设置 `OTEL_PHP_FIBERS_ENABLED=true`，可以把 `context.force_global_storage_without_ffi` 关掉。
