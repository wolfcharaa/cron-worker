<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule\Frequency;

use Wolfcharaa\Cron\Schedule\CronExpression;

abstract readonly class EveryTime
{
    final public function __construct(public int $interval = 1)
    {
        if ($this->interval < 1 || $this->interval > $this->maximumInterval()) {
            throw new \InvalidArgumentException(\sprintf(
                '%s interval must be between 1 and %d, %d given.',
                static::class,
                $this->maximumInterval(),
                $this->interval,
            ));
        }
    }

    final public static function minutes(int $interval = 1): EveryMinutes
    {
        return new EveryMinutes($interval);
    }

    final public static function hours(int $interval = 1): EveryHours
    {
        return new EveryHours($interval);
    }

    final public static function days(int $interval = 1): EveryDays
    {
        return new EveryDays($interval);
    }

    final public static function months(int $interval = 1): EveryMonths
    {
        return new EveryMonths($interval);
    }

    abstract public function expression(): CronExpression;

    abstract protected function maximumInterval(): int;

    final protected function stepRule(): string
    {
        return $this->interval === 1 ? '*' : '*/' . $this->interval;
    }
}
