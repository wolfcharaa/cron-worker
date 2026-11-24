<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Configuration;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\CronTaskDefinition;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Configuration\SymfonyCommandTaskConfig;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Internal\Task\ContainerServiceTaskHandler;
use Wolfcharaa\Cron\Internal\Task\ExecTaskHandler;
use Wolfcharaa\Cron\Internal\Task\ScheduledTask;
use Wolfcharaa\Cron\Internal\Task\SymfonyCommandTaskHandler;
use Wolfcharaa\Cron\Internal\Task\Task;
use Wolfcharaa\Cron\Internal\Task\TaskHandler;

/** @internal */
final readonly class TaskConfigCompiler
{
    /** @param \Closure(): ContainerInterface|null $containerFactory */
    public function __construct(private ?\Closure $containerFactory) {}

    public function compile(CronTaskDefinition $definition, string $profile): ScheduledTask
    {
        $path = \sprintf('profiles.%s.tasks.%s', $profile, $definition->name);

        return new ScheduledTask(
            new Task(
                $definition->name,
                $this->handler($definition->task, $path),
                $definition->task->overlapPolicy(),
                $this->chain($definition->chain, $path),
            ),
            $definition->expression,
            $definition->profiles,
        );
    }

    private function chain(ChainConfig $chain, string $path): ?Task
    {
        $next = null;
        $tasks = $chain->tasks();

        foreach (array_reverse($tasks, true) as $position => $config) {
            $taskPath = $path . '.then.' . $position . '.' . $config->name();
            $next = new Task(
                $config->name(),
                $this->handler($config, $taskPath),
                $config->overlapPolicy(),
                $next,
            );
        }

        return $next;
    }

    private function handler(
        ServiceTaskConfig|SymfonyCommandTaskConfig|ExecTaskConfig $config,
        string $path,
    ): TaskHandler {
        if ($config instanceof ExecTaskConfig) {
            return new ExecTaskHandler(
                $config->command,
                $config->workingDirectory,
                $config->environment,
                $config->shell,
                $config->expectedUser,
                $config->outputFile,
            );
        }

        $factory = $this->containerFactory ?? throw InvalidCronConfiguration::at(
            '<definitions>',
            $path,
            'a container factory is required for service and Symfony command tasks.',
        );

        if ($config instanceof ServiceTaskConfig) {
            if (!array_is_list($config->arguments)) {
                throw InvalidCronConfiguration::at('<definitions>', $path, 'service arguments must be a positional list.');
            }

            return new ContainerServiceTaskHandler(
                $factory,
                $config->serviceId,
                $config->method,
                $config->arguments,
            );
        }

        return new SymfonyCommandTaskHandler($factory, $config->commandId, $config->input);
    }
}
