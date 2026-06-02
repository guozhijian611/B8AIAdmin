<?php

declare(strict_types=1);

namespace Adminer {
    function header(string $header, bool $replace = true, int $responseCode = 0): bool
    {
        $GLOBALS['__adminer_headers'] ??= [];
        $GLOBALS['__adminer_status'] ??= 200;

        if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/i', $header, $matches)) {
            $GLOBALS['__adminer_status'] = (int) $matches[1];
            return true;
        }

        if ($responseCode > 0) {
            $GLOBALS['__adminer_status'] = $responseCode;
        }

        if (stripos($header, 'Location:') === 0 && $GLOBALS['__adminer_status'] === 200) {
            $GLOBALS['__adminer_status'] = 302;
        }

        $name = strtolower(strtok($header, ':') ?: '');
        if ($replace && $name !== '' && $name !== 'set-cookie') {
            $GLOBALS['__adminer_headers'] = array_values(array_filter(
                $GLOBALS['__adminer_headers'],
                static fn (string $item): bool => strtolower(strtok($item, ':') ?: '') !== $name
            ));
        }

        $GLOBALS['__adminer_headers'][] = $header;
        return true;
    }

    function headers_sent(?string &$filename = null, ?int &$line = null): bool
    {
        $filename = null;
        $line = null;
        return false;
    }
}

namespace {
    $payload = json_decode(stream_get_contents(STDIN), true) ?: [];
    $metaFile = (string) ($payload['meta_file'] ?? '');

    $GLOBALS['__adminer_headers'] = [];
    $GLOBALS['__adminer_status'] = 200;

    register_shutdown_function(static function () use ($metaFile): void {
        if ($metaFile === '') {
            return;
        }

        file_put_contents($metaFile, json_encode([
            'status' => $GLOBALS['__adminer_status'] ?? 200,
            'headers' => $GLOBALS['__adminer_headers'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    });

    $_GET = $payload['get'] ?? [];
    $_POST = $payload['post'] ?? [];
    $_COOKIE = $payload['cookie'] ?? [];
    $_FILES = $payload['files'] ?? [];
    $_SERVER = array_merge($_SERVER, $payload['server'] ?? []);
    $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);

    chdir(__DIR__);
    include __DIR__ . '/adminer-5.4.2-mysql.php';
}
