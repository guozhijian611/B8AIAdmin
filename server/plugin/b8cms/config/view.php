<?php

use support\view\ThinkPHP;

return [
    'handler' => ThinkPHP::class,
    'options' => [
        'view_path' => base_path() . '/plugin/b8cms/app/view/',
        'view_suffix' => 'html',
    ],
];
