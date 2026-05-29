<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Bootstrap;

use OpenB8\WebmanOtelTrace\Logging\TraceLogProcessor;
use OpenB8\WebmanOtelTrace\Support\TraceContext;
use OpenTelemetry\API\Baggage\Propagation\BaggagePropagator;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\SpanExporterFactory;
use OpenTelemetry\Contrib\Otlp\StdoutSpanExporterFactory;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanExporter\LoggerExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use support\Log;
use think\facade\Db;
use Throwable;
use Webman\Bootstrap;
use Workerman\Worker;

class TraceBootstrap implements Bootstrap
{
    private static bool $started = false;
    private static bool $otlpWarningLogged = false;

    public static function start(?Worker $worker): void
    {
        if (self::$started || !config('plugin.openb8.webman-otel-trace.app.enable', true)) {
            return;
        }
        self::$started = true;

        try {
            self::registerSdk();
            self::registerLogProcessor();
            self::registerSqlLogListener();
        } catch (Throwable $throwable) {
            self::logBootstrapError($throwable);
        }
    }

    private static function registerSdk(): void
    {
        self::configureContextStorage();

        $exporterDriver = strtolower((string)config('plugin.openb8.webman-otel-trace.app.exporter.driver', 'stdout'));
        if ($exporterDriver === 'none') {
            return;
        }

        $spanExporter = self::createSpanExporter($exporterDriver);

        $sampleRate = max(0.0, min(1.0, (float)config('plugin.openb8.webman-otel-trace.app.trace.sample_rate', 1.0)));
        $resource = ResourceInfo::create(Attributes::create([
            'service.name' => (string)config('plugin.openb8.webman-otel-trace.app.service.name', 'b8aiadmin-webman'),
            'service.namespace' => (string)config('plugin.openb8.webman-otel-trace.app.service.namespace', 'openb8'),
            'service.version' => (string)config('plugin.openb8.webman-otel-trace.app.service.version', 'dev'),
            'deployment.environment.name' => (string)config('plugin.openb8.webman-otel-trace.app.service.environment', 'local'),
            'telemetry.sdk.language' => 'php',
        ]));

        $builder = TracerProvider::builder()
            ->setSampler(new ParentBased(new TraceIdRatioBasedSampler($sampleRate)))
            ->setResource($resource);

        $builder->addSpanProcessor(new SimpleSpanProcessor($spanExporter));

        $tracerProvider = $builder->build();

        $propagator = new MultiTextMapPropagator([
            TraceContextPropagator::getInstance(),
            BaggagePropagator::getInstance(),
        ]);

        Globals::reset();
        Globals::registerInitializer(
            static fn (Configurator $configurator): Configurator => $configurator
                ->withTracerProvider($tracerProvider)
                ->withPropagator($propagator)
        );
        ShutdownHandler::register($tracerProvider->shutdown(...));
    }

    private static function createSpanExporter(string $exporterDriver): mixed
    {
        if ($exporterDriver === 'otlp') {
            self::applyOtlpEnvironment();

            $endpoint = (string)config('plugin.openb8.webman-otel-trace.app.exporter.endpoint', 'http://127.0.0.1:4318');
            if (config('plugin.openb8.webman-otel-trace.app.exporter.check_endpoint', true)
                && config('plugin.openb8.webman-otel-trace.app.exporter.disable_on_unreachable', true)
                && !self::isEndpointReachable($endpoint)
            ) {
                self::logOtlpUnavailable($endpoint);
                return new LoggerExporter((string)config('plugin.openb8.webman-otel-trace.app.service.name', 'b8aiadmin-webman'));
            }

            return (new SpanExporterFactory())->create();
        }

        return (new StdoutSpanExporterFactory())->create();
    }

    private static function configureContextStorage(): void
    {
        if (!config('plugin.openb8.webman-otel-trace.app.context.force_global_storage_without_ffi', true)) {
            return;
        }

        if (extension_loaded('ffi')) {
            return;
        }

        Context::setStorage(new ContextStorage());
    }

    private static function applyOtlpEnvironment(): void
    {
        self::setEnv('OTEL_EXPORTER_OTLP_ENDPOINT', (string)config('plugin.openb8.webman-otel-trace.app.exporter.endpoint', ''));
        self::setEnv('OTEL_EXPORTER_OTLP_PROTOCOL', (string)config('plugin.openb8.webman-otel-trace.app.exporter.protocol', 'http/protobuf'));
        self::setEnv('OTEL_EXPORTER_OTLP_HEADERS', (string)config('plugin.openb8.webman-otel-trace.app.exporter.headers', ''));
        self::setEnv('OTEL_EXPORTER_OTLP_TIMEOUT', (string)config('plugin.openb8.webman-otel-trace.app.exporter.timeout', '10000'));
    }

    private static function setEnv(string $key, string $value): void
    {
        if ($value === '') {
            return;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private static function isEndpointReachable(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['host'])) {
            return true;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? 'http'));
        $host = (string)$parts['host'];
        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $timeout = max(0.05, (float)config('plugin.openb8.webman-otel-trace.app.exporter.check_timeout', 0.2));

        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!is_resource($connection)) {
            return false;
        }

        fclose($connection);
        return true;
    }

    private static function logOtlpUnavailable(string $endpoint): void
    {
        if (self::$otlpWarningLogged) {
            return;
        }
        self::$otlpWarningLogged = true;

        $message = 'OpenTelemetry OTLP endpoint 不可达，已临时关闭 trace 导出';
        try {
            Log::warning($message, [
                'endpoint' => $endpoint,
                'hint' => '启动 otel-collector/jaeger，或设置 OTEL_EXPORTER_OTLP_DISABLE_ON_UNREACHABLE=false 强制导出',
            ]);
        } catch (Throwable) {
            echo $message . ': ' . $endpoint . PHP_EOL;
        }
    }

    private static function registerLogProcessor(): void
    {
        Log::channel()->pushProcessor(new TraceLogProcessor());
    }

    private static function registerSqlLogListener(): void
    {
        $config = (array)config('plugin.openb8.webman-otel-trace.app.sql_log', []);
        if (!($config['enable'] ?? false) || !class_exists(Db::class)) {
            return;
        }

        Db::listen(static function ($sql, $runtime) use ($config): void {
            $sql = (string)$sql;
            $runtime = (float)$runtime;
            $ignoreSql = array_map('strtolower', (array)($config['ignore_sql'] ?? ['select 1']));
            if (in_array(strtolower(trim($sql)), $ignoreSql, true)) {
                return;
            }

            $trace = TraceContext::current();
            $payload = [
                'time' => date('Y-m-d H:i:s'),
                'trace_id' => $trace['trace_id'],
                'span_id' => $trace['span_id'],
                'sql' => $sql,
                'runtime_seconds' => $runtime,
            ];
            $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $sql;

            if ($config['console'] ?? false) {
                echo '[otel-sql] ' . $line . PHP_EOL;
            }

            if ($config['file'] ?? true) {
                try {
                    Log::channel('plugin.openb8.webman-otel-trace.sql')->debug($line);
                } catch (Throwable $throwable) {
                    echo '[otel-sql] 写入日志失败: ' . $throwable->getMessage() . PHP_EOL;
                }
            }
        });
    }

    private static function logBootstrapError(Throwable $throwable): void
    {
        try {
            Log::error('OpenTelemetry 初始化失败', [
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile() . ':' . $throwable->getLine(),
            ]);
        } catch (Throwable) {
            echo 'OpenTelemetry 初始化失败: ' . $throwable->getMessage() . PHP_EOL;
        }
    }
}
