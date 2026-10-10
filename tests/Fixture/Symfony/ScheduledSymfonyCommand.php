<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Symfony;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wolfcharaa\Cron\Attribute\SymfonyTask;

#[SymfonyTask(
    schedule: '*/5 * * * *',
    profiles: ['stage', 'prod'],
    name: 'symfony-cleanup',
    arguments: ['expired'],
)]
final class ScheduledSymfonyCommand extends Command
{
    public ?string $receivedScope = null;

    public bool $receivedHelper = false;

    protected function configure(): void
    {
        $this->addArgument('scope', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scope = $input->getArgument('scope');
        $this->receivedScope = \is_string($scope) ? $scope : null;
        $this->receivedHelper = $this->getHelperSet()?->has('fixture') ?? false;
        $output->writeln('scope=' . $this->receivedScope);

        return self::SUCCESS;
    }
}

final class FixtureHelper implements HelperInterface
{
    public function setHelperSet(?HelperSet $helperSet): void {}

    public function getHelperSet(): ?HelperSet
    {
        return null;
    }

    public function getName(): string
    {
        return 'fixture';
    }
}
