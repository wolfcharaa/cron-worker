<?php

declare(strict_types=1);

namespace App\CronExample;

use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;

#[CronTask(
    schedule: new EveryMinutes(5),
    profiles: ['prod'],
    name: 'refresh-search-index',
)]
final class RefreshSearchIndex
{
    public function __invoke(): int
    {
        return 0;
    }
}
