<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Discovery;

use Wolfcharaa\Cron\Attribute\CronTask;

#[CronTask(
    schedule: '* * * * *',
    name: 'recover-stale',
)]
final class RecoverStaleCommand
{
    public function __invoke(): int
    {
        return 0;
    }
}
