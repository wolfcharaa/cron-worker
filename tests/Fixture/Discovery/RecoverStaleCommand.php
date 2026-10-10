<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Discovery;

use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Configuration\Profile;

#[CronTask(
    schedule: '* * * * *',
    profiles: Profile::ANY,
    name: 'recover-stale',
)]
final class RecoverStaleCommand
{
    public function __invoke(): int
    {
        return 0;
    }
}
