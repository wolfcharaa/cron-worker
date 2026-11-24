<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule\Frequency;

use Wolfcharaa\Cron\Schedule\CronExpression;

/** Cron calendar-field shortcut for minute steps. */
final readonly class EveryMinutes
{
    public function __construct(public int $interval = 1)
    {
        self::guard($this->interval, 59);
    }

    /** Converts the calendar-field step to a cron expression. */
    public function expression(): CronExpression
    {
        return (new CronExpression())->withMinute($this->rule());
    }

    private function rule(): string
    {
        return $this->interval === 1 ? '*' : '*/' . $this->interval;
    }

    private static function guard(int $interval, int $maximum): void
    {
        if ($interval < 1 || $interval > $maximum) {
            throw new \InvalidArgumentException(\sprintf(
                '%s interval must be between 1 and %d, %d given.',
                self::class,
                $maximum,
                $interval,
            ));
        }
    }
}
