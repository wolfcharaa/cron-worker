<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Builder\CrontabConfigBuilder;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;

#[Test]
final class CrontabConfigBuilderTest
{
    public function buildsExecTasksFromUserCrontab(): void
    {
        $map = (new CrontabConfigBuilder(<<<'CRON'
            SHELL=/bin/bash
            PATH=/usr/local/bin:/usr/bin
            # cron:name=cleanup
            */15 * * * * php bin/console app:cleanup
            @daily php bin/console app:archive
            CRON, profile: 'prod'))->build();

        $tasks = $map->forProfile('prod');
        $first = $tasks[0] ?? throw new \LogicException('First crontab task is missing.');
        $second = $tasks[1] ?? throw new \LogicException('Second crontab task is missing.');

        Assert::same($first->name, 'cleanup');
        Assert::same($first->expression->toString(), '*/15 * * * *');
        Assert::instanceOf($first->task, ExecTaskConfig::class);
        Assert::same($second->expression->toString(), '0 0 * * *');
    }

    public function readsSystemCrontabUserColumn(): void
    {
        $map = (new CrontabConfigBuilder(
            '# cron:name=health' . "\n" . '*/5 * * * * www-data php bin/health.php',
            profile: 'stage',
            systemFormat: true,
        ))->build();
        $task = $map->forProfile('stage')[0] ?? throw new \LogicException('System crontab task is missing.');

        Assert::instanceOf($task->task, ExecTaskConfig::class);
        Assert::same($task->task->command, 'php bin/health.php');
    }
}
