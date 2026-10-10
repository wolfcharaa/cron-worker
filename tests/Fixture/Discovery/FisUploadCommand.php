<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Discovery;

use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Task\OverlapPolicy;

#[CronTask(
    schedule: '*/10 * * * *',
    profiles: ['default', 'server-10.11.166.100'],
    name: 'fis-upload',
    method: 'run',
    arguments: ['prepared'],
    overlapPolicy: OverlapPolicy::Allow,
    chain: new ChainConfig(
        new ServiceTaskConfig(
            serviceId: ReindexCommand::class,
            method: 'run',
            taskName: 'fis-reindex',
        ),
        new ServiceTaskConfig(
            serviceId: NotifyCommand::class,
            taskName: 'fis-notify',
        ),
    ),
)]
#[CronTask(
    schedule: '0 * * * *',
    profiles: 'server-10.11.159.86',
    name: 'fis-upload',
    method: 'run',
)]
final class FisUploadCommand
{
    public function run(string $state = 'formed'): int
    {
        return $state === 'prepared' ? 0 : 1;
    }
}
