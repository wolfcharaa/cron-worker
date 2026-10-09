<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Task;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Exception\InvalidTaskResult;
use Wolfcharaa\Cron\Schedule\CronSchedule;
use Wolfcharaa\Cron\Task\ScheduledTask;

#[Test]
final class ScheduledTaskTest
{
    public function treatsVoidResultAsSuccessfulExit(): void
    {
        $task = new ScheduledTask('void-task', new CronSchedule('* * * * *'), static function (): void {});

        Assert::same($task->execute(), 0);
    }

    public function preservesExplicitExitCode(): void
    {
        $task = new ScheduledTask('exit-task', new CronSchedule('* * * * *'), static fn(): int => 17);

        Assert::same($task->execute(), 17);
    }

    public function rejectsInvalidResult(): void
    {
        $task = new ScheduledTask('invalid-task', new CronSchedule('* * * * *'), static fn(): string => 'done');

        Expect::exception(InvalidTaskResult::class);
        $task->execute();
    }
}
