<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Symfony;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wolfcharaa\Cron\Attribute\SymfonyTask;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\SymfonyCommandTaskConfig;
use Wolfcharaa\Cron\Configuration\SymfonyInput;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;

#[SymfonyTask(
    schedule: new EveryMinutes(5),
    profiles: ['stage', 'prod'],
    name: 'symfony-cleanup',
    input: new SymfonyInput(
        arguments: ['scope' => 'expired'],
        options: ['limit' => 50, 'force' => true],
    ),
    chain: new ChainConfig(
        new SymfonyCommandTaskConfig(
            commandId: FollowUpSymfonyCommand::class,
            input: new SymfonyInput(
                arguments: ['message' => 'done'],
                options: ['uppercase' => true],
            ),
            taskName: 'symfony-follow-up',
        ),
    ),
)]
final class ScheduledSymfonyCommand extends Command
{
    public ?string $receivedScope = null;

    public ?int $receivedLimit = null;

    public bool $receivedForce = false;

    public bool $receivedHelper = false;

    public bool $receivedInteractive = true;

    protected function configure(): void
    {
        $this
            ->addArgument('scope', InputArgument::REQUIRED)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED)
            ->addOption('force', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scope = $input->getArgument('scope');
        $limit = $input->getOption('limit');
        $this->receivedScope = \is_string($scope) ? $scope : null;
        $this->receivedLimit = \is_int($limit) ? $limit : null;
        $this->receivedForce = (bool) $input->getOption('force');
        $this->receivedHelper = $this->getHelperSet()?->has('fixture') ?? false;
        $this->receivedInteractive = $input->isInteractive();
        $output->writeln('scope=' . $this->receivedScope);

        return self::SUCCESS;
    }
}
