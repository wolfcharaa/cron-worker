<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Exception\DuplicateTask;
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
}
