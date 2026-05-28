<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace\Logging;

use OpenB8\WebmanOtelTrace\Support\TraceContext;

class TraceLogProcessor
{
    public function __invoke($record)
    {
        $context = TraceContext::current();

        if (!$context['trace_id']) {
            return $record;
        }

        if (is_array($record)) {
            $record['extra']['trace_id'] = $context['trace_id'];
            $record['extra']['span_id'] = $context['span_id'];
            return $record;
        }

        if (is_object($record) && property_exists($record, 'extra')) {
            $record->extra['trace_id'] = $context['trace_id'];
            $record->extra['span_id'] = $context['span_id'];
        }

        return $record;
    }
}
