<?php

declare(strict_types=1);

namespace OpenB8\WebmanOtelTrace;

class Install
{
    public const WEBMAN_PLUGIN = true;

    protected static array $pathRelation = [
        'config/plugin/openb8/webman-otel-trace' => 'config/plugin/openb8/webman-otel-trace',
    ];

    public static function install(bool $isInstall = true): void
    {
        static::installByRelation();
    }

    public static function uninstall(): void
    {
        static::uninstallByRelation();
    }

    public static function installByRelation(): void
    {
        foreach (static::$pathRelation as $source => $dest) {
            if ($pos = strrpos($dest, '/')) {
                $parentDir = base_path() . '/' . substr($dest, 0, $pos);
                if (!is_dir($parentDir)) {
                    mkdir($parentDir, 0777, true);
                }
            }
            copy_dir(__DIR__ . '/../' . $source, base_path() . '/' . $dest);
            echo "Create $dest\n";
        }
    }

    public static function uninstallByRelation(): void
    {
        foreach (static::$pathRelation as $source => $dest) {
            $path = base_path() . '/' . $dest;
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            echo "Remove $dest\n";
            if (is_file($path) || is_link($path)) {
                unlink($path);
                continue;
            }
            remove_dir($path);
        }
    }
}
