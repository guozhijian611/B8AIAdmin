<?php

declare(strict_types=1);

namespace app\command;

use Symfony\Component\Console\Command\Command;

abstract class AbstractPhinxCommand extends Command
{
    protected function runPhinx(array $arguments, array $successExitCodes = [0]): int
    {
        $command = array_merge([
            PHP_BINARY,
            base_path('vendor/bin/phinx'),
            '-c',
            base_path('../Database/phinx.php'),
        ], $arguments);

        $process = proc_open(
            $command,
            [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ],
            $pipes,
            base_path()
        );

        if (!is_resource($process)) {
            return self::FAILURE;
        }

        $exitCode = proc_close($process);
        return in_array($exitCode, $successExitCodes, true) ? self::SUCCESS : $exitCode;
    }
}
