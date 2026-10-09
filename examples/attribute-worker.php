<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\Loader\AttributeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Discovery\ClassListProvider;
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Scheduler;

require dirname(__DIR__) . '/vendor/autoload.php';

#[CronTask(
    schedule: '*/10 * * * *',
    profile: 'default',
    name: 'import',
    method: 'run',
    chain: new ChainConfig(
        new ServiceTaskConfig(ReindexCommand::class, method: 'run', taskName: 'reindex'),
        new ServiceTaskConfig(NotifyCommand::class, taskName: 'notify'),
    ),
)]
#[CronTask(
    schedule: '*/5 * * * *',
    profile: 'stage',
    name: 'import',
    method: 'run',
)]
final class ImportCommand
{
    public function run(): int
    {
        return 0;
    }
}

final class ReindexCommand
{
    public function run(): int
    {
        return 0;
    }
}

final class NotifyCommand
{
    public function __invoke(): int
    {
        return 0;
    }
}

$configuredProfile = getenv('APP_CRON_PROFILE');
$activeProfile = is_string($configuredProfile) && $configuredProfile !== ''
    ? $configuredProfile
    : 'default';

$scheduler = new Scheduler(
    options: new SchedulerOptions(profile: $activeProfile),
    containerFactory: static function (): ContainerInterface {
        throw new LogicException('Replace this callback with the application bootstrap.');
    },
);

$scheduler
    ->load(new AttributeCronConfigLoader(
        new ClassListProvider([ImportCommand::class]),
    ))
    ->run();
