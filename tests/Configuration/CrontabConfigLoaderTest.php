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
            HOME=/var/www/app
            CRON_TZ=Europe/Moscow
            # An ordinary crontab comment.
            */15 * * * * php bin/console app:cleanup
            @daily php bin/console app:archive
            CRON, profile: 'prod'))->load();

        $tasks = $map->forProfile('prod');
        $first = $tasks[0] ?? throw new \LogicException('First crontab task is missing.');
        $second = $tasks[1] ?? throw new \LogicException('Second crontab task is missing.');

        Assert::true(str_starts_with($first->name, 'exec-'));
        Assert::same($first->expression->toString(), '*/15 * * * *');
        Assert::same($first->timezone->getName(), 'Europe/Moscow');
        Assert::instanceOf($first->task, ExecTaskConfig::class);
        Assert::same($first->task->workingDirectory, '/var/www/app');
        Assert::same($second->expression->toString(), '0 0 * * *');
    }

    public function readsSystemCrontabUserColumn(): void
    {
        $map = (new CrontabConfigLoader(
            '# health check' . "\n" . '*/5 * * * * www-data php bin/health.php',
            profile: 'stage',
            systemFormat: true,
        ))->load();
        $task = $map->forProfile('stage')[0] ?? throw new \LogicException('System crontab task is missing.');

        Assert::instanceOf($task->task, ExecTaskConfig::class);
        Assert::same($task->task->command, 'php bin/health.php');
        Assert::same($task->task->expectedUser, 'www-data');
    }

    public function loadsExecConfigurationWithoutContainer(): void
    {
        $scheduler = new Scheduler(options: new SchedulerOptions(profile: 'prod'));
        $scheduler->load(new CrontabConfigLoader(
            "# exits successfully\n* * * * * exit 0",
            profile: 'prod',
        ));
        $task = array_values($scheduler->tasks())[0] ?? throw new \LogicException('Exec task is missing.');

        Assert::same($task->execute(), 0);
    }

    public function keepsDuplicateClassicEntriesAsSeparateTasks(): void
    {
        $tasks = (new CrontabConfigLoader(<<<'CRON'
            * * * * * php bin/heartbeat.php
            * * * * * php bin/heartbeat.php
            CRON))->load()->forProfile('default');
        $first = $tasks[0] ?? throw new \LogicException('First duplicate task is missing.');
        $second = $tasks[1] ?? throw new \LogicException('Second duplicate task is missing.');

        Assert::same(\count($tasks), 2);
        Assert::false($first->name === $second->name);
    }
}
