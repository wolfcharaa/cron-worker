<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Exception\DuplicateTask;
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Scheduler;

#[Test]
final class SchedulerTest
{
    public function rejectsDuplicateTaskNames(): void
    {
        $scheduler = new Scheduler();
        $scheduler->cron('same-name', '* * * * *', static function (): void {});

        Expect::exception(DuplicateTask::class);
        $scheduler->cron('same-name', '*/5 * * * *', static function (): void {});
    }

    public function registersTaskThroughBuilder(): void
    {
        $scheduler = new Scheduler();
        $task = $scheduler
            ->task('cleanup', static fn(): int => 0)
            ->everyMinutes(15)
            ->weekdays()
            ->build();

        Assert::same($task->name, 'cleanup');
    }

    public function rejectsDuplicateBuilderTaskNames(): void
    {
        $scheduler = new Scheduler();
        $scheduler->task('same-name', static function (): void {});

        Expect::exception(DuplicateTask::class);
        $scheduler->task('same-name', static function (): void {});
    }

    public function selectsTasksFromNamedDeploymentProfile(): void
    {
        $scheduler = new Scheduler(new SchedulerOptions(profile: 'server-10.11.166.100'));
        $scheduler->task('shared', static function (): void {});
        $scheduler->task('application-only', static function (): void {})->profile('server-10.11.159.86');
        $scheduler
            ->task('ftp', static function (): void {})
            ->profiles('default', 'server-10.11.166.100');

        Assert::same(array_keys($scheduler->tasks()), ['shared', 'ftp']);
    }

    public function rejectsProfileWithoutTasks(): void
    {
        $scheduler = new Scheduler(new SchedulerOptions(profile: 'prod'));
        $scheduler->task('development', static function (): void {})->profile('dev');

        Expect::exception(\LogicException::class);
        $scheduler->tasks();
    }
}
