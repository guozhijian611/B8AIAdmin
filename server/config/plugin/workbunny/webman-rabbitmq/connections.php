<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Workbunny\WebmanRabbitMQ\Connection\Connection;

$boolEnv = static function (string $name, bool $default = false): bool {
    $value = env($name, $default);
    if (is_bool($value)) {
        return $value;
    }
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
};

return [
    'default' => [
        'connection'       => Connection::class,
        // 连接池，用于支撑影子模式
        'connections_pool' => [
            'min_connections'       => 1,
            'max_connections'       => 20,
            'idle_timeout'          => 60,
            'wait_timeout'          => 10,
        ],
        'config' => [
            'debug'              => $boolEnv('RABBITMQ_DEBUG', false),
            'host'               => env('RABBITMQ_HOST', '127.0.0.1'),
            'vhost'              => env('RABBITMQ_VHOST', '/'),
            'port'               => (int) env('RABBITMQ_PORT', 5672),
            'username'           => env('RABBITMQ_USERNAME', 'admin'),
            'password'           => env('RABBITMQ_PASSWORD', 'admin'),
            'mechanism'          => 'AMQPLAIN',
            'timeout'            => (int) env('RABBITMQ_TIMEOUT', 10),
            // 重启间隔
            'restart_interval'   => (int) env('RABBITMQ_RESTART_INTERVAL', 5),
            // 通道池
            'channels_pool'      => [
                'idle_timeout'     => 60,
                'wait_timeout'     => 10,
            ],
            'client_properties' => [
                'name'     => 'workbunny/webman-rabbitmq',
                'version'  => InstalledVersions::getVersion('workbunny/webman-rabbitmq'),
            ],
            // 心跳回调 callable
            'heartbeat_callback' => function () {
            },

            // see https://www.workerman.net/doc/workerman/async-tcp-connection/construct.html
//            'context' => [
//                'ssl' => [
//                    'verify_peer'      => false,
//                    'verify_peer_name' => false,
//                ],
//            ]
        ],
    ],
];
