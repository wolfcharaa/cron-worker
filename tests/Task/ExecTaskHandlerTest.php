<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Task;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

#[Test]
final class ExecTaskHandlerTest
{
    public function returnsExternalCommandExitCode(): void
    {
        $config = new ExecTaskConfig(
            taskName: 'exit-seven',
            command: escapeshellarg(PHP_BINARY) . " -r 'exit(7);'",
        );
        $now = new \DateTimeImmutable();

        Assert::same(
            $config->createHandler(null)->execute(new TaskExecutionContext('exit-seven', $now, $now)),
            7,
        );
    }

    public function validatesUserAndAppendsCommandOutput(): void
    {
        $account = posix_getpwuid(posix_geteuid());

        if ($account === false) {
            throw new \RuntimeException('Unable to resolve the current Unix user.');
        }

        $user = $account['name'];
        $output = tempnam(sys_get_temp_dir(), 'cron-exec-');

        if ($output === false) {
            throw new \RuntimeException('Unable to create command output file.');
        }

        $config = new ExecTaskConfig(
            taskName: 'write-output',
            command: 'printf cron-output',
            expectedUser: $user,
            outputFile: $output,
        );
        $now = new \DateTimeImmutable();

        try {
            Assert::same(
                $config->createHandler(null)->execute(new TaskExecutionContext('write-output', $now, $now)),
                0,
            );
            Assert::same(file_get_contents($output), 'cron-output');
        } finally {
            unlink($output);
        }
    }
}
