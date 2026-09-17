<?php

use support\Request;

return [
    'debug' => true,
    'controller_suffix' => 'Controller',
    'controller_reuse' => false,
    'version' => '6.1.0',
    // MVP 默认关闭上游在线市场；见 docs/market-upstream.md
    'market_upstream_enabled' => env('MARKET_UPSTREAM_ENABLED', 'false'),
    'market_upstream_base_url' => env('MARKET_UPSTREAM_BASE_URL', 'https://saas.saithink.top/dev-api'),
];
