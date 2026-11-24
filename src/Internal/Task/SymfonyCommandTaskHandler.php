<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Task;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Wolfcharaa\Cron\Configuration\SymfonyInput;
use Wolfcharaa\Cron\TaskExecutionContext;

/** @internal */
final readonly class SymfonyCommandTaskHandler implements TaskHandler
{
    /** @param \Closure(): ContainerInterface $containerFactory */
    public function __construct(
        private \Closure $containerFactory,
        private string $commandId,
        private SymfonyInput $input,
    ) {}

    public function execute(TaskExecutionContext $_context): int
    {
        $container = ($this->containerFactory)();
        $command = $container->get($this->commandId);

        if (!$command instanceof Command) {
            throw new \UnexpectedValueException(\sprintf(
                'Container service "%s" must be an instance of %s.',
                $this->commandId,
                Command::class,
            ));
        }

        if ($container->has(HelperSet::class)) {
            $helpers = $container->get(HelperSet::class);

            if (!$helpers instanceof HelperSet) {
                throw new \UnexpectedValueException(\sprintf('Container service "%s" must be an instance of %s.', HelperSet::class, HelperSet::class));
            }

            $command->setHelperSet($helpers);
        }

        $input = new ArrayInput($this->input->parameters(), $command->getDefinition());
        $input->setInteractive(false);

        return $command->run($input, $this->output($container));
    }

    private function output(ContainerInterface $container): OutputInterface
    {
        if (!$container->has(OutputInterface::class)) {
            return new ConsoleOutput();
        }

        $output = $container->get(OutputInterface::class);

        if (!$output instanceof OutputInterface) {
            throw new \UnexpectedValueException(\sprintf('Container service "%s" must implement %s.', OutputInterface::class, OutputInterface::class));
        }

        return $output;
    }
}
