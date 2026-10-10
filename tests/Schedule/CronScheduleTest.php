<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Schedule;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Schedule\CronSchedule;

#[Test]
final class CronScheduleTest
{
    public function calculatesNextMinuteWithoutPolling(): void
    {
        $schedule = new CronSchedule('* * * * *');

        $nextRun = $schedule->nextRunAfter(new \DateTimeImmutable('2026-10-09 12:34:56 UTC'));

        Assert::same($nextRun->format('Y-m-d H:i:s P'), '2026-10-09 12:35:00 +00:00');
    }

    public function usesTimezoneFromRuntimeDate(): void
    {
        $schedule = new CronSchedule('0 0 * * *');
        $after = new \DateTimeImmutable('2026-10-09 20:59:00 UTC');
        $after = $after->setTimezone(new \DateTimeZone('Europe/Moscow'));

        $nextRun = $schedule->nextRunAfter($after);

        Assert::same($nextRun->format('Y-m-d H:i:s P'), '2026-10-10 00:00:00 +03:00');
    }

    public function acceptsConfiguredExpressionObject(): void
    {
        $expression = (new CronExpression())
            ->withMinute(15)
            ->withHour(10);
        $schedule = new CronSchedule($expression);

        $nextRun = $schedule->nextRunAfter(new \DateTimeImmutable('2026-10-09 09:59:00 UTC'));

        Assert::same($schedule->expression, $expression);
        Assert::same($nextRun->format('Y-m-d H:i:s P'), '2026-10-09 10:15:00 +00:00');
    }
}
