<?php

declare(strict_types=1);

namespace app\command;

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractPhinxCommand extends Command
{
    private const PLUGIN_ENV = 'B8_PHINX_PLUGIN';

    protected function runPhinx(array $arguments, array $successExitCodes = [0], ?OutputInterface $output = null, ?string $plugin = null): int
    {
        $output ??= new ConsoleOutput();
        try {
            $plugin = $this->normalizePluginName($plugin);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return self::FAILURE;
        }

        if ($plugin !== null && !is_dir($this->pluginMigrationPath($plugin))) {
            $output->writeln("<error>插件 {$plugin} 没有独立迁移目录：plugin/{$plugin}/db/migrations</error>");
            return self::FAILURE;
        }

        $command = array_merge([
            'phinx',
            '-c',
            $this->databaseFile('phinx.php'),
        ], $arguments);

        $application = new PhinxApplication();
        $application->setAutoExit(false);

        $previousPlugin = getenv(self::PLUGIN_ENV);
        $this->setPluginEnv($plugin);

        try {
            $exitCode = $application->doRun(new ArgvInput($command), $output);
        } finally {
            $this->restorePluginEnv($previousPlugin);
        }

        return in_array($exitCode, $successExitCodes, true) ? self::SUCCESS : $exitCode;
    }

    protected function pluginOption(InputInterface $input): ?string
    {
        $plugin = (string) ($input->getOption('plugin') ?? '');
        $plugin = trim($plugin);
        return $plugin === '' ? null : $plugin;
    }

    protected function databaseFile(string $file): string
    {
        $candidates = [
            base_path(false) . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . $file,
            base_path('../Database/' . $file),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    private function normalizePluginName(?string $plugin): ?string
    {
        if ($plugin === null) {
            return null;
        }

        $plugin = trim($plugin);
        if ($plugin === '') {
            return null;
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $plugin)) {
            throw new \InvalidArgumentException('插件名称只能包含小写字母、数字和下划线，且必须以字母开头。');
        }

        return $plugin;
    }

    private function pluginMigrationPath(string $plugin): string
    {
        return base_path(false) . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . $plugin
            . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'migrations';
    }

    private function setPluginEnv(?string $plugin): void
    {
        if ($plugin === null) {
            putenv(self::PLUGIN_ENV);
            unset($_ENV[self::PLUGIN_ENV], $_SERVER[self::PLUGIN_ENV]);
            return;
        }

        putenv(self::PLUGIN_ENV . '=' . $plugin);
        $_ENV[self::PLUGIN_ENV] = $plugin;
        $_SERVER[self::PLUGIN_ENV] = $plugin;
    }

    private function restorePluginEnv(string|false $previousPlugin): void
    {
        if ($previousPlugin === false) {
            putenv(self::PLUGIN_ENV);
            unset($_ENV[self::PLUGIN_ENV], $_SERVER[self::PLUGIN_ENV]);
            return;
        }

        putenv(self::PLUGIN_ENV . '=' . $previousPlugin);
        $_ENV[self::PLUGIN_ENV] = $previousPlugin;
        $_SERVER[self::PLUGIN_ENV] = $previousPlugin;
    }
}
