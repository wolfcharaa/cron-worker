<?php

declare(strict_types=1);

use Wolfcharaa\Cron\Scheduler;
use Wolfcharaa\Cron\TaskExecutionContext;

require dirname(__DIR__) . '/vendor/autoload.php';

$scheduler = new Scheduler();
$scheduler
    ->task('heartbeat', static function (TaskExecutionContext $context): int {
        fwrite(STDOUT, 'cron-heartbeat:' . $context->taskName . "\n");
        fflush(STDOUT);

        return 0;
    })
    ->everyMinute();
$scheduler->run();
