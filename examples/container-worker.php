<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Scheduler;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

require dirname(__DIR__) . '/vendor/autoload.php';

interface ImportCommand
{
    public function run(): int;
}

interface NotifyService
{
    public function completed(string $taskName): void;
}

// The factory must bootstrap a fresh application container or child scope.
$containerFactory = static function (): ContainerInterface {
    throw new LogicException('Replace this callback with the application bootstrap.');
};

$scheduler = new Scheduler(containerFactory: $containerFactory);

$scheduler
    ->serviceTask('import', ImportCommand::class, 'run')
    ->everyMinutes(15)
    ->thenContainer(
        'notify',
        static function (ContainerInterface $container, TaskExecutionContext $context): void {
            $notifier = $container->get(NotifyService::class);

            if (!$notifier instanceof NotifyService) {
                throw new UnexpectedValueException('Notify service has an invalid type.');
            }

            $notifier->completed($context->taskName);
        },
    );

$scheduler->run();
