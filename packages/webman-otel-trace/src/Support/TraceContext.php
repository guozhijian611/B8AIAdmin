<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Support;

use OpenTelemetry\API\Trace\Span;

class TraceContext
{
    /** @return array{trace_id: ?string, span_id: ?string, sampled: bool} */
    public static function current(): array
    {
        $context = Span::getCurrent()->getContext();

        if (!$context->isValid()) {
            return [
                'trace_id' => null,
                'span_id' => null,
                'sampled' => false,
            ];
        }

        return [
            'trace_id' => $context->getTraceId(),
            'span_id' => $context->getSpanId(),
            'sampled' => $context->isSampled(),
        ];
    }
}
