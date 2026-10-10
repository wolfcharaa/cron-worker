<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Schedule;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Schedule\Frequency\EveryTime;
use Wolfcharaa\Cron\Schedule\ScheduleBuilder;

#[Test]
final class ScheduleBuilderTest
{
    public function mapsReadableIntervalsToCronExpressions(): void
    {
        Assert::same(
            ScheduleBuilder::create()->everyMinutes(15)->expression()->toString(),
            '*/15 * * * *',
        );
        Assert::same(
            ScheduleBuilder::create()->everyHours(4)->expression()->toString(),
            '0 */4 * * *',
        );
        Assert::same(
            ScheduleBuilder::create()->everyDays(3)->expression()->toString(),
            '0 0 */3 * *',
        );
        Assert::same(
            ScheduleBuilder::create()->everyMonths(2)->expression()->toString(),
            '0 0 1 */2 *',
        );
    }

    public function acceptsTypedEveryTimeConfiguration(): void
    {
        $builder = ScheduleBuilder::create()->every(EveryTime::minutes(20));

        Assert::same($builder->expression()->toString(), '*/20 * * * *');
    }

    public function buildsDailyWeekdayScheduleWithoutCronSyntax(): void
    {
        $schedule = ScheduleBuilder::create()
            ->dailyAt(9, 30)
            ->weekdays()
            ->build();

        $after = new \DateTimeImmutable('2026-10-09 07:00:00 UTC');
        $nextRun = $schedule->nextRunAfter($after->setTimezone(new \DateTimeZone('Europe/Moscow')));

        Assert::same($schedule->expression->toString(), '30 9 * * MON,TUE,WED,THU,FRI');
        Assert::same($nextRun->format('Y-m-d H:i:s P'), '2026-10-12 09:30:00 +03:00');
    }

    public function supportsClassicCronAsEscapeHatch(): void
    {
        $builder = ScheduleBuilder::fromCron('5 4 1 * *');

        Assert::same($builder->expression()->toString(), '5 4 1 * *');
    }

    public function rejectsUnsupportedMinuteInterval(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        EveryTime::minutes(60);
    }

    public function requiresAtLeastOneWeekday(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        ScheduleBuilder::create()->onDaysOfWeek();
    }
}
