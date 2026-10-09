<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule;

use Cron\CronExpression;

final readonly class CronSchedule implements Schedule
{
    private CronExpression $cron;

    private \DateTimeZone $timezone;

    public function __construct(
        public string $expression,
        \DateTimeZone|string $timezone = 'UTC',
    ) {
        $this->cron = new CronExpression($expression);
        $this->timezone = \is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;
    }

    public function nextRunAfter(\DateTimeImmutable $after): \DateTimeImmutable
    {
        $nextRun = $this->cron->getNextRunDate(
            currentTime: $after,
            allowCurrentDate: false,
            timeZone: $this->timezone->getName(),
        );

        return \DateTimeImmutable::createFromMutable($nextRun);
    }

    public function timezone(): \DateTimeZone
    {
        return $this->timezone;
    }
}
