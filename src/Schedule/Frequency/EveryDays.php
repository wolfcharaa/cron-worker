<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule\Frequency;

use Wolfcharaa\Cron\Schedule\CronExpression;

final readonly class EveryDays extends EveryTime
{
    public function expression(): CronExpression
    {
        return (new CronExpression())
            ->withMinute(0)
            ->withHour(0)
            ->withDayOfMonth($this->stepRule());
    }

    protected function maximumInterval(): int
    {
        return 31;
    }
}
