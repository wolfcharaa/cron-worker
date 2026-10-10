<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Chain;

use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\ContainerTaskConfig;
use Wolfcharaa\Cron\Configuration\StaticTaskConfig;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;

#[CronTask(
    schedule: new EveryMinutes(5),
    profiles: 'test',
    name: 'configured-chain',
    chain: new ChainConfig(
        new StaticTaskConfig(
            class: StaticChainAction::class,
            method: 'call',
            arguments: [7],
            taskName: 'static-step',
        ),
        new ContainerTaskConfig(
            class: ContainerChainAction::class,
            method: 'call',
            arguments: [3],
            taskName: 'container-step',
        ),
    ),
)]
final class ConfiguredChainCommand
{
    public function __invoke(): int
    {
        return 0;
    }
}
