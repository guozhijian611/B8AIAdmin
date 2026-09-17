<?php

return [
    '' => [
        plugin\saiadmin\app\middleware\CheckLogin::class,
        plugin\saiadmin\app\middleware\CheckAuth::class,
    ]
];
