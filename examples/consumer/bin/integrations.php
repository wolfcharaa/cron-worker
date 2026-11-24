<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wolfcharaa\Cron\Configuration\CronProfileMap;
use Wolfcharaa\Cron\Configuration\CronTaskDefinition;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\SymfonyCommandTaskConfig;
use Wolfcharaa\Cron\Scheduler;

require dirname(__DIR__) . '/vendor/autoload.php';

final class ConsumerService
{
    public function __invoke(): int
    {
        fwrite(STDOUT, "cron-service\n");
        fflush(STDOUT);

        return 0;
    }
}

final class ConsumerCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('cron-symfony');

        return self::SUCCESS;
    }
}

final class ConsumerContainer implements ContainerInterface
{
    public function get(string $id): mixed
    {
        return match ($id) {
            ConsumerService::class => new ConsumerService(),
            ConsumerCommand::class => new ConsumerCommand(),
            default => throw new RuntimeException('Unknown service: ' . $id),
        };
    }

    public function has(string $id): bool
    {
        return $id === ConsumerService::class || $id === ConsumerCommand::class;
    }
}

$scheduler = new Scheduler(
    containerFactory: static fn(): ContainerInterface => new ConsumerContainer(),
);
$scheduler->serviceTask('service', ConsumerService::class)->everyMinute();
$scheduler->load(new CronProfileMap([
    new CronTaskDefinition(
        new SymfonyCommandTaskConfig(ConsumerCommand::class, taskName: 'symfony'),
        '* * * * *',
    ),
    new CronTaskDefinition(
        new ExecTaskConfig('exec', "printf 'cron-exec\\n'"),
        '* * * * *',
    ),
]));
$scheduler->run();
