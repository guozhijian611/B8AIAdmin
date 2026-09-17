<?php

use plugin\saiadmin\app\middleware\SystemLog;
use plugin\saiadmin\app\middleware\CheckLogin;
use plugin\saiadmin\app\middleware\CheckAuth;
use plugin\saiuser\app\middleware\CheckMemberLogin;

return [
    'admin' => [
        CheckLogin::class,
        CheckAuth::class,
        SystemLog::class,
    ],
    'api' => [
        CheckMemberLogin::class,
    ]
];
