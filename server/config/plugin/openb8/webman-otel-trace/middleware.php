<?php

declare(strict_types=1);

return [
    '@' => [
        OpenB8\WebmanOtelTrace\Middleware\TraceMiddleware::class,
        OpenB8\WebmanOtelTrace\Middleware\RequestLogMiddleware::class,
    ],
];
