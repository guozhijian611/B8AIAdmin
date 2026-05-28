<?php

declare(strict_types=1);

use OpenB8\WebmanOtelTrace\Controller\MetricsController;
use Webman\Route;

if (config('plugin.openb8.webman-otel-trace.app.enable', true)
    && config('plugin.openb8.webman-otel-trace.app.metrics.enable', true)
) {
    Route::get(config('plugin.openb8.webman-otel-trace.app.metrics.path', '/metrics'), [MetricsController::class, 'index']);
}
