<?php
$enabled = env('LOG_READER_ENABLED', null);

return [
    'enable' => $enabled === null || $enabled === ''
        ? config('app.debug', false)
        : in_array(strtolower((string) $enabled), ['1', 'true', 'on', 'yes'], true),
];
