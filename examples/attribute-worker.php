<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Discovery\AttributeCronTaskDiscovery;
use Wolfcharaa\Cron\Discovery\ClassListProvider;
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Scheduler;

require dirname(__DIR__) . '/vendor/autoload.php';

#[CronTask(
    schedule: '*/10 * * * *',
    profiles: ['default', 'stage'],
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

$profiles = (new AttributeCronTaskDiscovery())->discover(
    new ClassListProvider([ImportCommand::class]),
);

$scheduler = new Scheduler(
    options: new SchedulerOptions(profile: 'stage'),
    containerFactory: static function (): ContainerInterface {
        throw new LogicException('Replace this callback with the application bootstrap.');
    },
);

$scheduler->load($profiles)->run();
