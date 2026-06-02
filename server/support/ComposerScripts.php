<?php

namespace support;

class ComposerScripts
{
    public static function install($event): void
    {
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
}
