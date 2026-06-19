<?php

return [
    'http' => [
        'allowed_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => trim($host),
            explode(',', (string) env('SAIBOARD_HTTP_ALLOWED_HOSTS', ''))
        ))),
    ],
    'runtime' => [
        'rate_limit' => [
            'enabled' => filter_var(env('SAIBOARD_RATE_LIMIT', true), FILTER_VALIDATE_BOOLEAN),
            'ip' => [
                'window' => (int) env('SAIBOARD_RATE_LIMIT_IP_WINDOW', 60),
                'limit' => (int) env('SAIBOARD_RATE_LIMIT_IP_LIMIT', 240),
            ],
            'screen' => [
                'window' => (int) env('SAIBOARD_RATE_LIMIT_SCREEN_WINDOW', 60),
                'limit' => (int) env('SAIBOARD_RATE_LIMIT_SCREEN_LIMIT', 3000),
            ],
            'owner' => [
                'window' => (int) env('SAIBOARD_RATE_LIMIT_OWNER_WINDOW', 60),
                'limit' => (int) env('SAIBOARD_RATE_LIMIT_OWNER_LIMIT', 6000),
            ],
        ],
        'lock' => [
            'redis' => filter_var(env('SAIBOARD_REDIS_LOCK', true), FILTER_VALIDATE_BOOLEAN),
            'ttl' => (int) env('SAIBOARD_LOCK_TTL', 15),
        ],
        'metrics' => [
            'enabled' => filter_var(env('SAIBOARD_RUNTIME_METRICS', true), FILTER_VALIDATE_BOOLEAN),
            'window' => (int) env('SAIBOARD_RUNTIME_METRICS_WINDOW', 300),
        ],
    ],
];
