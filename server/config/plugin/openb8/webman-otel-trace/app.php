<?php

declare(strict_types=1);

$bool = static function (string $name, bool $default): bool {
    $value = getenv($name);
    return $value === false ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
};

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
        'driver' => getenv('OTEL_TRACES_EXPORTER') ?: 'none',
        'endpoint' => getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'http://127.0.0.1:4318',
        'protocol' => getenv('OTEL_EXPORTER_OTLP_PROTOCOL') ?: 'http/protobuf',
        'headers' => getenv('OTEL_EXPORTER_OTLP_HEADERS') ?: '',
        'timeout' => (int)(getenv('OTEL_EXPORTER_OTLP_TIMEOUT') ?: 10000),
        'check_endpoint' => $bool('OTEL_EXPORTER_OTLP_CHECK_ENDPOINT', true),
        'check_timeout' => (float)(getenv('OTEL_EXPORTER_OTLP_CHECK_TIMEOUT') ?: 0.2),
        'disable_on_unreachable' => $bool('OTEL_EXPORTER_OTLP_DISABLE_ON_UNREACHABLE', true),
    ],
    'trace' => [
        'sample_rate' => (float)(getenv('OTEL_TRACES_SAMPLER_ARG') ?: 1.0),
        'response_trace_header' => true,
        'capture_business_code' => true,
        'business_success_codes' => [0, 200],
        'max_response_body_parse_length' => 8192,
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
        // memory | file，file 可以聚合 Webman 多 worker 指标，适合 Prometheus 抓取。
        'storage' => getenv('OTEL_METRICS_STORAGE') ?: 'file',
        'file' => runtime_path() . '/otel-trace/metrics.json',
    ],
    'request_log' => [
        'enable' => $bool('OTEL_REQUEST_LOG', true),
        'console' => $bool('OTEL_REQUEST_LOG_CONSOLE', false),
        'file' => $bool('OTEL_REQUEST_LOG_FILE', true),
        'include_headers' => false,
        'include_request_body' => true,
        'include_response_body' => true,
        'max_body_length' => 4096,
        'ignore_paths' => [
            '/metrics',
        ],
        'sensitive_fields' => [
            'authorization',
            'cookie',
            'password',
            'passwd',
            'token',
            'access_token',
            'refresh_token',
            'secret',
        ],
    ],
    'sql_log' => [
        // ThinkORM 底层 PDO 已经会产生 span；这里是给人看的 SQL 文件日志，默认关闭避免和 thinkorm-log 重复。
        'enable' => $bool('OTEL_SQL_LOG', false),
        'console' => $bool('OTEL_SQL_LOG_CONSOLE', false),
        'file' => $bool('OTEL_SQL_LOG_FILE', true),
        'ignore_sql' => [
            'select 1',
        ],
    ],
    'rabbitmq' => [
        'enable' => true,
    ],
];
