<?php
return [
    'default' => [
        'host' => 'redis://' . env('REDIS_HOST', '127.0.0.1') . ':' . env('REDIS_PORT', 6379),
        'options' => [
            'auth' => env('REDIS_PASSWORD', ''),
            'db' => (int) env('REDIS_DB', 0),
            'prefix' => env('REDIS_QUEUE_PREFIX', ''),
            'max_attempts'  => (int) env('REDIS_QUEUE_MAX_ATTEMPTS', 5),
            'retry_seconds' => (int) env('REDIS_QUEUE_RETRY_SECONDS', 5),
        ],
        // Connection pool, supports only Swoole or Swow drivers.
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 1,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ]
    ],
];
