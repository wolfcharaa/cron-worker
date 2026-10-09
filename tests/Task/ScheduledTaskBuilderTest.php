<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Task;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Schedule\CronSchedule;
use Wolfcharaa\Cron\Task\OverlapPolicy;
use Wolfcharaa\Cron\Task\ScheduledTaskBuilder;

#[Test]
final class ScheduledTaskBuilderTest
{
    public function buildsTaskUsingReadableScheduleApi(): void
    {
        $task = (new ScheduledTaskBuilder('cleanup', static fn(): int => 7))
            ->everyMinutes(15)
            ->weekdays()
            ->timezone('Europe/Moscow')
            ->allowOverlapping()
            ->build();

        Assert::instanceOf($task->schedule, CronSchedule::class);
        Assert::same($task->schedule->expression->toString(), '*/15 * * * MON,TUE,WED,THU,FRI');
        Assert::same($task->schedule->timezone()->getName(), 'Europe/Moscow');
        Assert::same($task->overlapPolicy, OverlapPolicy::Allow);
        Assert::same($task->execute(), 7);
    }

    public function buildsOneShotSuccessChain(): void
    {
        $builder = new ScheduledTaskBuilder('first', static fn(): int => 0);
        $builder
            ->then('second', static fn(): int => 0)
            ->then('third', static fn(): int => 0);

        $task = $builder->build();

        Assert::same($task->next?->name, 'second');
        Assert::same($task->next->next?->name, 'third');
        Assert::same($task->next->next->next, null);
    }
}
