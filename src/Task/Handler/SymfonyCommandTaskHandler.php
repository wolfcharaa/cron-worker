<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task\Handler;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Wolfcharaa\Cron\Configuration\SymfonyInput;
use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

final readonly class SymfonyCommandTaskHandler implements TaskHandler
{
    /**
     * @param class-string<Command> $commandId
     */
    public function __construct(
        private ContainerFactory $containerFactory,
        private string $commandId,
        private SymfonyInput $input = new SymfonyInput(),
    ) {}

    public function execute(TaskExecutionContext $_context): int
    {
        $container = $this->containerFactory->create();
        $command = $this->command($container);

        if ($container->has(HelperSet::class)) {
            $helperSet = $container->get(HelperSet::class);

            if (!$helperSet instanceof HelperSet) {
                throw new \UnexpectedValueException(\sprintf(
                    'Container service "%s" must be an instance of %s.',
                    HelperSet::class,
                    HelperSet::class,
                ));
            }

            $command->setHelperSet($helperSet);
        }

        $input = new ArrayInput($this->input->parameters(), $command->getDefinition());
        $input->setInteractive(false);

        return $command->run($input, $this->output($container));
    }

    private function command(ContainerInterface $container): Command
    {
        if ($container->has($this->commandId)) {
            $command = $container->get($this->commandId);
        } else {
            $reflection = new \ReflectionClass($this->commandId);
            $constructor = $reflection->getConstructor();

            if (!$reflection->isInstantiable() || ($constructor?->getNumberOfRequiredParameters() ?? 0) > 0) {
                throw new \UnexpectedValueException(\sprintf(
                    'Symfony command "%s" requires container registration.',
                    $this->commandId,
                ));
            }

            $command = $reflection->newInstance();
        }

        if (!$command instanceof Command) {
            throw new \UnexpectedValueException(\sprintf(
                'Container service "%s" must be an instance of %s.',
                $this->commandId,
                Command::class,
            ));
        }

        return $command;
    }

    private function output(ContainerInterface $container): OutputInterface
    {
        if (!$container->has(OutputInterface::class)) {
            return new ConsoleOutput();
        }

        $output = $container->get(OutputInterface::class);

        if (!$output instanceof OutputInterface) {
            throw new \UnexpectedValueException(\sprintf(
                'Container service "%s" must implement %s.',
                OutputInterface::class,
                OutputInterface::class,
            ));
        }

        return $output;
    }
}
