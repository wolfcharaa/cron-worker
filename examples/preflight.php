<?php

declare(strict_types=1);

use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;
use Wolfcharaa\Cron\Scheduler;
use Wolfcharaa\Cron\SchedulerOptions;

require dirname(__DIR__) . '/vendor/autoload.php';

$catalog = YamlConfigLoader::fromFile(__DIR__ . '/cron.yaml')->load();

foreach ($catalog->profiles() as $profile) {
    (new Scheduler(new SchedulerOptions(profile: $profile)))->load($catalog);
}

fwrite(STDOUT, "cron configuration is valid\n");
