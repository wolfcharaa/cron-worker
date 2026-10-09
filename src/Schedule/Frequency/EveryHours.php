<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule\Frequency;

use Wolfcharaa\Cron\Schedule\CronExpression;

final readonly class EveryHours extends EveryTime
{
    public function expression(): CronExpression
    {
        return (new CronExpression())
            ->withMinute(0)
            ->withHour($this->stepRule());
    }

    protected function maximumInterval(): int
    {
        return 23;
    }
}
