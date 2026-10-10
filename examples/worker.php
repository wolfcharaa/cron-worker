<?php

declare(strict_types=1);

use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Scheduler;

require dirname(__DIR__) . '/vendor/autoload.php';

$scheduler = new Scheduler(new SchedulerOptions(
    maxConcurrency: 2,
    timezone: 'Europe/Moscow',
));

$scheduler
    ->task('heartbeat', static function (): void {
        fwrite(STDOUT, sprintf("[%s] child %d executed heartbeat\n", date(DATE_ATOM), getmypid()));
    })
    ->everyMinute();

$scheduler->run();
