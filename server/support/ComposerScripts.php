<?php

namespace support;

class ComposerScripts
{
    public static function postInstall($event): void
    {
        static::ensureEnvFile($event);
    }

    public static function install($event): void
    {
        static::ensureEnvFile($event);

        if (!static::loadWebmanPlugin()) {
            return;
        }

        Plugin::install($event);
    }

    public static function uninstall($event): void
    {
        if (!static::loadWebmanPlugin()) {
            return;
        }

        Plugin::uninstall($event);
    }

    private static function loadWebmanPlugin(): bool
    {
        if (class_exists(Plugin::class)) {
            return true;
        }

        $pluginFile = dirname(__DIR__) . '/vendor/workerman/webman-framework/src/support/Plugin.php';
        if (is_file($pluginFile)) {
            require_once $pluginFile;
        }

        return class_exists(Plugin::class);
    }

    private static function ensureEnvFile($event): void
    {
        $basePath = dirname(__DIR__);
        $env = $basePath . '/.env';
        $example = $basePath . '/.env.example';

        if (is_file($env) || !is_file($example)) {
            return;
        }

        copy($example, $env);

        $io = method_exists($event, 'getIO') ? $event->getIO() : null;
        if ($io) {
            $io->write('<info>已从 .env.example 生成 .env。</info>');
            $io->write('<comment>请执行 php webman b8:install 配置数据库并完成首次安装。</comment>');
        }
    }
}
