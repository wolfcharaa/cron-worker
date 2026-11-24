<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule\Frequency;

use Wolfcharaa\Cron\Schedule\CronExpression;

/** Cron calendar-field shortcut for hour steps. */
final readonly class EveryHours
{
    public function __construct(public int $interval = 1)
    {
        if ($this->interval < 1 || $this->interval > 23) {
            throw new \InvalidArgumentException(\sprintf(
                '%s interval must be between 1 and 23, %d given.',
                self::class,
                $this->interval,
            ));
        }
    }

    /** Converts the calendar-field step to a cron expression. */
    public function expression(): CronExpression
    {
        return (new CronExpression())
            ->withMinute(0)
            ->withHour($this->interval === 1 ? '*' : '*/' . $this->interval);
    }
}
