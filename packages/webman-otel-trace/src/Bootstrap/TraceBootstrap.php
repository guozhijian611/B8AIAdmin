<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Bootstrap;

use OpenB8\WebmanOtelTrace\Logging\TraceLogProcessor;
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
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use support\Log;
use Throwable;
use Webman\Bootstrap;
use Workerman\Worker;

class TraceBootstrap implements Bootstrap
{
    private static bool $started = false;

    public static function start(?Worker $worker): void
    {
        if (self::$started || !config('plugin.openb8.webman-otel-trace.app.enable', true)) {
            return;
        }
        self::$started = true;

        try {
            self::registerSdk();
            self::registerLogProcessor();
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

        self::applyOtlpEnvironment();

        $spanExporter = $exporterDriver === 'otlp'
            ? (new SpanExporterFactory())->create()
            : (new StdoutSpanExporterFactory())->create();

        $sampleRate = max(0.0, min(1.0, (float)config('plugin.openb8.webman-otel-trace.app.trace.sample_rate', 1.0)));
        $resource = ResourceInfo::create(Attributes::create([
            'service.name' => (string)config('plugin.openb8.webman-otel-trace.app.service.name', 'b8aiadmin-webman'),
            'service.namespace' => (string)config('plugin.openb8.webman-otel-trace.app.service.namespace', 'openb8'),
            'service.version' => (string)config('plugin.openb8.webman-otel-trace.app.service.version', 'dev'),
            'deployment.environment.name' => (string)config('plugin.openb8.webman-otel-trace.app.service.environment', 'local'),
            'telemetry.sdk.language' => 'php',
        ]));

        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($spanExporter))
            ->setSampler(new ParentBased(new TraceIdRatioBasedSampler($sampleRate)))
            ->setResource($resource)
            ->build();

        $propagator = new MultiTextMapPropagator([
            TraceContextPropagator::getInstance(),
            BaggagePropagator::getInstance(),
        ]);

        Globals::registerInitializer(
            static fn (Configurator $configurator): Configurator => $configurator
                ->withTracerProvider($tracerProvider)
                ->withPropagator($propagator)
        );
        ShutdownHandler::register($tracerProvider->shutdown(...));
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

    private static function registerLogProcessor(): void
    {
        Log::channel()->pushProcessor(new TraceLogProcessor());
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
