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
    function prepare_adminer_session(array $databaseConfig, string $sessionId): void
    {
        if ($sessionId === '') {
            return;
        }

        $server = (string) ($databaseConfig['server'] ?? '127.0.0.1');
        $username = (string) ($databaseConfig['username'] ?? 'root');
        $password = (string) ($databaseConfig['password'] ?? '');
        $database = (string) ($databaseConfig['database'] ?? '');

        session_name('adminer_sid');
        session_id($sessionId);
        session_start();

        $_SESSION['pwds']['server'][$server][$username] = $password;
        if ($database !== '') {
            $_SESSION['db']['server'][$server][$username][$database] = true;
        }
        $_SESSION['token'] ??= random_int(1, 1000000);

        session_write_close();
        $_COOKIE['adminer_sid'] = $sessionId;
    }

    function adminer_object(): \Adminer\Adminer
    {
        return new class($GLOBALS['__saiadmin_database_config'] ?? []) extends \Adminer\Adminer {
            private array $databaseConfig;

            public function __construct(array $databaseConfig)
            {
                $this->databaseConfig = $databaseConfig;
            }

            public function credentials(): array
            {
                return [
                    $this->databaseConfig['server'] ?? '127.0.0.1',
                    $this->databaseConfig['username'] ?? 'root',
                    $this->databaseConfig['password'] ?? '',
                ];
            }

            public function database(): string
            {
                return (string) ($_GET['db'] ?? $this->databaseConfig['database'] ?? '');
            }

            public function login($username, $password)
            {
                $expectedUsername = (string) ($this->databaseConfig['username'] ?? '');
                $expectedPassword = (string) ($this->databaseConfig['password'] ?? '');

                if ($username !== $expectedUsername) {
                    return '仅允许使用后台 .env 配置的数据库账号登录';
                }

                if ($password !== '' && $password !== $expectedPassword) {
                    return '数据库密码与后台 .env 配置不一致';
                }

                return true;
            }

            public function loginFormField($field, $label, $input)
            {
                return match ($field) {
                    'server' => $label . '<input name="auth[server]" value="' . \Adminer\h((string) ($this->databaseConfig['server'] ?? '127.0.0.1')) . '" readonly>',
                    'username' => $label . '<input name="auth[username]" id="username" value="' . \Adminer\h((string) ($this->databaseConfig['username'] ?? 'root')) . '" readonly autocomplete="username">',
                    'password' => $label . '<input type="password" name="auth[password]" value="" placeholder="已从后台 .env 读取" autocomplete="off">',
                    'db' => $label . '<input name="auth[db]" value="' . \Adminer\h((string) ($this->databaseConfig['database'] ?? '')) . '" readonly>',
                    default => parent::loginFormField($field, $label, $input),
                } . "\n";
            }
        };
    }

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

    $databaseConfig = $payload['database_config'] ?? [];
    prepare_adminer_session($databaseConfig, (string) ($payload['adminer_session_id'] ?? ''));

    $_GET = $payload['get'] ?? [];
    $_POST = $payload['post'] ?? [];
    $_COOKIE = $payload['cookie'] ?? [];
    if (!empty($payload['adminer_session_id'])) {
        $_COOKIE['adminer_sid'] = (string) $payload['adminer_session_id'];
    }
    $_FILES = $payload['files'] ?? [];
    $_SERVER = array_merge($_SERVER, $payload['server'] ?? []);
    $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
    $GLOBALS['__saiadmin_database_config'] = $databaseConfig;

    chdir(__DIR__);
    include __DIR__ . '/adminer-5.4.2-mysql.php';
}
