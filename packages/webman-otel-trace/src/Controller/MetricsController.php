<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Controller;

use OpenB8\WebmanOtelTrace\Metrics\MetricRegistry;
use support\Response;

class MetricsController
{
    public function index(): Response
    {
        return response(MetricRegistry::render(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
