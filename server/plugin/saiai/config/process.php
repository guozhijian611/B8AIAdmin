<?php

return [
    'saiai_realtime_gateway' => [
        'handler' => plugin\saiai\app\process\AliyunRealtimeGateway::class,
        'listen' => 'websocket://0.0.0.0:' . env('SAIAI_REALTIME_WS_PORT', 8791),
        'count' => (int) env('SAIAI_REALTIME_WS_COUNT', 1),
        'reloadable' => true,
    ],
];
