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

        if (static::shouldSkipSaiadminPlugin($event, 'install')) {
            return;
        }

        if (!static::loadWebmanPlugin()) {
            return;
        }

        Plugin::install($event);
    }

    public static function uninstall($event): void
    {
        if (static::shouldSkipSaiadminPlugin($event, 'uninstall')) {
            return;
        }

        if (!static::loadWebmanPlugin()) {
            return;
        }

        Plugin::uninstall($event);
    }

    private static function shouldSkipSaiadminPlugin($event, string $action): bool
    {
        if (!method_exists($event, 'getOperation')) {
            return false;
        }
        
        $operation = $event->getOperation();
        $package = method_exists($operation, 'getPackage') ? $operation->getPackage() : (method_exists($operation, 'getTargetPackage') ? $operation->getTargetPackage() : null);
        
        if (!$package || $package->getName() !== 'saithink/saiadmin') {
            return false;
        }

        $pluginPath = dirname(__DIR__) . '/plugin/saiadmin';
        if (!is_dir($pluginPath)) {
            // Allow copy_dir if SoT doesn't exist
            return false;
        }

        if (getenv('FORCE_SAIADMIN_PLUGIN_INSTALL') == '1') {
            return false;
        }

        $io = method_exists($event, 'getIO') ? $event->getIO() : null;
        if ($io) {
            $io->write("<warning>检测到 server/plugin/saiadmin (SoT) 已存在，已跳过 saithink/saiadmin 的 {$action} 操作，防止覆盖/删除本地定制。</warning>");
            $io->write("<info>若需强制覆盖，请设置环境变量 FORCE_SAIADMIN_PLUGIN_INSTALL=1</info>");
        }

        return true;
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
