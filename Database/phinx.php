<?php

$serverPath = dirname(__DIR__) . '/server';
$autoload = $serverPath . '/vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
}

if (class_exists(\Dotenv\Dotenv::class) && is_file($serverPath . '/.env')) {
    if (method_exists(\Dotenv\Dotenv::class, 'createUnsafeMutable')) {
        \Dotenv\Dotenv::createUnsafeMutable($serverPath)->load();
    } else {
        \Dotenv\Dotenv::createMutable($serverPath)->load();
    }
}

$env = static function (string $name, mixed $default = null): mixed {
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
};

$plugin = (string) $env('B8_PHINX_PLUGIN', '');
$migrationsPath = __DIR__ . '/migrations';
$seedsPath = __DIR__ . '/seeds';
$migrationTable = 'phinxlog';

if ($plugin !== '') {
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $plugin)) {
        throw new RuntimeException('B8_PHINX_PLUGIN 只能包含小写字母、数字和下划线，且必须以字母开头。');
    }

    $migrationsPath = $serverPath . '/plugin/' . $plugin . '/db/migrations';
    if (!is_dir($migrationsPath)) {
        throw new RuntimeException("插件 {$plugin} 没有独立迁移目录：plugin/{$plugin}/db/migrations");
    }

    $pluginSeedsPath = $serverPath . '/plugin/' . $plugin . '/db/seeds';
    $seedsPath = is_dir($pluginSeedsPath) ? $pluginSeedsPath : $seedsPath;
    $migrationTable = 'phinxlog_' . $plugin;
}

return [
    'paths' => [
        'migrations' => $migrationsPath,
        'seeds' => $seedsPath,
    ],
    'environments' => [
        'default_migration_table' => $migrationTable,
        'default_environment' => 'default',
        'default' => [
            'adapter' => $env('DB_TYPE', 'mysql'),
            'host' => $env('DB_HOST', '127.0.0.1'),
            'name' => $env('DB_NAME', 'saiadmin'),
            'user' => $env('DB_USER', 'root'),
            'pass' => $env('DB_PASSWORD', '123456'),
            'port' => (int) $env('DB_PORT', 3306),
            'charset' => $env('DB_CHARSET', 'utf8mb4'),
            'collation' => $env('DB_COLLATION', 'utf8mb4_general_ci'),
            'table_prefix' => $env('DB_PREFIX', ''),
        ],
    ],
    'version_order' => 'creation',
];
