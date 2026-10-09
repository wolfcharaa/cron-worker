<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\Loader\CrontabConfigLoader;
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Scheduler;

#[Test]
final class CrontabConfigLoaderTest
{
    public function loadsExecTasksFromUserCrontab(): void
    {
        $map = (new CrontabConfigLoader(<<<'CRON'
            SHELL=/bin/bash
            PATH=/usr/local/bin:/usr/bin
            # cron:name=cleanup
            */15 * * * * php bin/console app:cleanup
            @daily php bin/console app:archive
            CRON, profile: 'prod'))->load();

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
        $map = (new CrontabConfigLoader(
            '# cron:name=health' . "\n" . '*/5 * * * * www-data php bin/health.php',
            profile: 'stage',
            systemFormat: true,
        ))->load();
        $task = $map->forProfile('stage')[0] ?? throw new \LogicException('System crontab task is missing.');

        Assert::instanceOf($task->task, ExecTaskConfig::class);
        Assert::same($task->task->command, 'php bin/health.php');
    }

    public function loadsExecConfigurationWithoutContainer(): void
    {
        $scheduler = new Scheduler(options: new SchedulerOptions(profile: 'prod'));
        $scheduler->load(new CrontabConfigLoader(
            "# cron:name=success\n* * * * * exit 0",
            profile: 'prod',
        ));
        $task = $scheduler->tasks()['success'] ?? throw new \LogicException('Exec task is missing.');

        Assert::same($task->execute(), 0);
    }
}
