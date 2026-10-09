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
}
