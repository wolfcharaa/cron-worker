<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Schedule;

use Testo\Assert;
use Testo\Test;
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

    public function appliesScheduleTimezone(): void
    {
        $schedule = new CronSchedule('0 0 * * *', new \DateTimeZone('Europe/Moscow'));

        $nextRun = $schedule->nextRunAfter(new \DateTimeImmutable('2026-10-09 20:59:00 UTC'));

        Assert::same($nextRun->format('Y-m-d H:i:s P'), '2026-10-10 00:00:00 +03:00');
    }
}
