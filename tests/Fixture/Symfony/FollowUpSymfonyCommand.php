<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Symfony;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class FollowUpSymfonyCommand extends Command
{
    public ?string $receivedMessage = null;

    protected function configure(): void
    {
        $this
            ->addArgument('message', InputArgument::REQUIRED)
            ->addOption('uppercase', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = $input->getArgument('message');
        $this->receivedMessage = \is_string($message) ? $message : null;

        if ((bool) $input->getOption('uppercase')) {
            $this->receivedMessage = strtoupper((string) $this->receivedMessage);
        }

        $output->writeln('message=' . $this->receivedMessage);

        return self::SUCCESS;
    }
}
