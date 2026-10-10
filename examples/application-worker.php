<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Configuration\Loader\AttributeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\Loader\CompositeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\Loader\CronConfigLoaderInterface;
use Wolfcharaa\Cron\Configuration\Loader\CrontabConfigLoader;
use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;
use Wolfcharaa\Cron\Discovery\Psr4DirectoryClassProvider;
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Scheduler;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$configuredProfile = getenv('APP_CRON_PROFILE');
$activeProfile = is_string($configuredProfile) && $configuredProfile !== ''
    ? $configuredProfile
    : 'default';
$configuredTimezone = getenv('APP_TIMEZONE');
$timezone = is_string($configuredTimezone) && $configuredTimezone !== ''
    ? $configuredTimezone
    : 'UTC';

$containerBootstrap = $projectRoot . '/config/container.php';
$containerFactory = is_file($containerBootstrap)
    ? static function () use ($containerBootstrap): ContainerInterface {
        $container = require $containerBootstrap;

        if (!$container instanceof ContainerInterface) {
            throw new UnexpectedValueException(sprintf(
                '%s must return a PSR-11 container.',
                $containerBootstrap,
            ));
        }

        return $container;
    }
: null;

/** @var list<CronConfigLoaderInterface> $loaders */
$loaders = [
    new AttributeCronConfigLoader(
        new Psr4DirectoryClassProvider([
            'App\Command\\' => $projectRoot . '/src/Command',
        ]),
    ),
];

$yamlConfig = $projectRoot . '/config/crontab.yaml';

if (is_file($yamlConfig)) {
    $loaders[] = YamlConfigLoader::fromFile($yamlConfig);
}

$classicCrontab = $projectRoot . '/config/crontab';

if (is_file($classicCrontab)) {
    $loaders[] = CrontabConfigLoader::fromFile(
        path: $classicCrontab,
        profile: $activeProfile,
    );
}

$scheduler = new Scheduler(
    options: new SchedulerOptions(
        profile: $activeProfile,
        maxConcurrency: 4,
        timezone: $timezone,
    ),
    containerFactory: $containerFactory,
);

// Ручные task()/serviceTask() можно зарегистрировать здесь до load().
$scheduler
    ->load(new CompositeCronConfigLoader(...$loaders))
    ->run();
