<?php

declare(strict_types=1);

namespace App\CronExample;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wolfcharaa\Cron\Attribute\SymfonyTask;
use Wolfcharaa\Cron\Configuration\SymfonyInput;

#[AsCommand(name: 'app:refresh-cache')]
#[SymfonyTask(
    schedule: '15 * * * *',
    profiles: ['prod'],
    input: new SymfonyInput(options: ['scope' => 'public']),
)]
final class RefreshCacheCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('scope', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('cache refreshed');

        return self::SUCCESS;
    }
}
