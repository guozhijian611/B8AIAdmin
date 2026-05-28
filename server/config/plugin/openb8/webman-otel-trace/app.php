<?php

declare(strict_types=1);

return [
    'enable' => true,
    'service' => [
        'name' => getenv('OTEL_SERVICE_NAME') ?: 'b8aiadmin-webman',
        'namespace' => getenv('OTEL_SERVICE_NAMESPACE') ?: 'openb8',
        'version' => getenv('OTEL_SERVICE_VERSION') ?: 'dev',
        'environment' => getenv('APP_ENV') ?: getenv('OTEL_DEPLOYMENT_ENVIRONMENT') ?: 'local',
    ],
    'exporter' => [
        // none | stdout | otlp
        'driver' => getenv('OTEL_TRACES_EXPORTER') ?: 'stdout',
        'endpoint' => getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'http://127.0.0.1:4318',
        'protocol' => getenv('OTEL_EXPORTER_OTLP_PROTOCOL') ?: 'http/protobuf',
        'headers' => getenv('OTEL_EXPORTER_OTLP_HEADERS') ?: '',
        'timeout' => (int)(getenv('OTEL_EXPORTER_OTLP_TIMEOUT') ?: 10000),
    ],
    'trace' => [
        'sample_rate' => (float)(getenv('OTEL_TRACES_SAMPLER_ARG') ?: 1.0),
        'response_trace_header' => true,
        'capture_request_headers' => [
            'user-agent',
            'x-request-id',
        ],
    ],
    'context' => [
        // 当前项目未启用 ext-ffi，OpenTelemetry 默认 fiber storage 会让 PDO 自动埋点在 Webman fiber 中告警。
        // 开启后使用进程级 ContextStorage；若以后启用 FFI + OTEL_PHP_FIBERS_ENABLED，可关闭它。
        'force_global_storage_without_ffi' => true,
    ],
    'metrics' => [
        'enable' => true,
        'path' => '/metrics',
    ],
    'rabbitmq' => [
        'enable' => true,
    ],
];
