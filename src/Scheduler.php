<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wolfcharaa\Cron\Configuration\CronProfileMap;
use Wolfcharaa\Cron\Configuration\Loader\CronConfigLoaderInterface;
use Wolfcharaa\Cron\Exception\CronRuntimeException;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Internal\Configuration\TaskConfigCompiler;
use Wolfcharaa\Cron\Internal\Runtime\PcntlSchedulerRuntime;
use Wolfcharaa\Cron\Internal\Task\CallableTaskHandler;
use Wolfcharaa\Cron\Internal\Task\ContainerServiceTaskHandler;
use Wolfcharaa\Cron\Internal\Task\ScheduledTask;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Task\OverlapPolicy;
use Wolfcharaa\Cron\Task\ScheduledTaskBuilder;

/**
 * Registers, validates, and runs one long-lived fork-based cron worker.
 *
 * Use {@see task()} for an application callable, {@see serviceTask()} for a
 * child-local PSR-11 service, or {@see load()} for an immutable configuration
 * map or loader. Each registration returns or contains a schedule; {@see run()}
 * starts the blocking master loop and returns only after graceful shutdown.
 *
 * The master owns scheduling, overlap checks, concurrency, signals, and child
 * process groups. Application containers and external connections must be
 * created by the optional container factory inside a forked child. A scheduler
 * instance is configured for one profile and is not reusable after `run()`.
 *
 * Typical manual task:
 *
 *     $scheduler = new Scheduler(
 *         new SchedulerOptions(profile: 'production', timezone: 'UTC'),
 *     );
 *     $scheduler->task('heartbeat', static function (TaskExecutionContext $context): int {
 *         echo $context->taskName, PHP_EOL;
 *
 *         return 0;
 *     })->everyMinute()->withoutOverlapping();
 *     $scheduler->run();
 *
 * Configuration failures are reported before the master loop through
 * {@see InvalidCronConfiguration}; fatal master-process failures use
 * {@see CronRuntimeException}. Child failures are
 * logged and represented by their exit status rather than rethrown in master.
 */
final class Scheduler
{
    /** @var array<string, ScheduledTask|ScheduledTaskBuilder> */
    private array $tasks = [];

    private readonly SchedulerOptions $options;

    private readonly LoggerInterface $logger;

    /** @var \Closure(): ContainerInterface|null */
    private readonly ?\Closure $containerFactory;

    /**
     * Creates one worker composition root without opening child resources.
     *
     * @param (callable(): ContainerInterface)|null $containerFactory
     */
    public function __construct(
        ?SchedulerOptions $options = null,
        ?LoggerInterface $logger = null,
        ?callable $containerFactory = null,
    ) {
        $this->options = $options ?? new SchedulerOptions();
        $this->logger = $logger ?? new NullLogger();
        $this->containerFactory = $containerFactory === null
            ? null
            : self::normalizeContainerFactory($containerFactory);
    }

    /**
     * Registers a manual callable and returns its fluent schedule builder.
     *
     * @param (callable(): (int|null))|(callable(TaskExecutionContext): (int|null)) $handler
     */
    public function task(string $name, callable $handler): ScheduledTaskBuilder
    {
        $this->guardUnique($name);
        $builder = ScheduledTaskBuilder::assemble(
            $name,
            new CallableTaskHandler($handler, '$.tasks.' . $name),
            $this->containerFactory,
        );
        $this->tasks[$name] = $builder;

        return $builder;
    }

    /**
     * Registers a child-local PSR-11 service task.
     *
     * @param array<int|string, mixed> $arguments
     */
    public function serviceTask(
        string $name,
        string $serviceId,
        ?string $method = null,
        array $arguments = [],
    ): ScheduledTaskBuilder {
        $this->guardUnique($name);

        if (!array_is_list($arguments)) {
            throw InvalidCronConfiguration::at('<manual>', '$.tasks.' . $name, 'service arguments must be a positional list.');
        }

        self::guardService($serviceId, $method, '$.tasks.' . $name);

        $factory = $this->containerFactory ?? throw InvalidCronConfiguration::at(
            '<manual>',
            '$.tasks.' . $name,
            'a container factory is required for service tasks.',
        );
        $builder = ScheduledTaskBuilder::assemble(
            $name,
            new ContainerServiceTaskHandler($factory, $serviceId, $method, $arguments),
            $factory,
        );
        $this->tasks[$name] = $builder;

        return $builder;
    }

    /**
     * Registers a callable with an exact cron expression.
     *
     * @param (callable(): (int|null))|(callable(TaskExecutionContext): (int|null)) $handler
     */
    public function cron(
        string $name,
        CronExpression|string $expression,
        callable $handler,
        OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ): self {
        $builder = $this->task($name, $handler)->cron($expression);

        if ($overlapPolicy === OverlapPolicy::Allow) {
            $builder->allowOverlapping();
        }

        return $this;
    }

    /** Atomically compiles and adds the active profile. */
    public function load(CronProfileMap|CronConfigLoaderInterface $profiles): self
    {
        $map = $profiles instanceof CronConfigLoaderInterface ? $profiles->load() : $profiles;
        $definitions = $map->forProfile($this->options->profile);
        $compiled = [];
        $compiler = new TaskConfigCompiler($this->containerFactory);

        foreach ($definitions as $definition) {
            if (isset($this->tasks[$definition->name]) || isset($compiled[$definition->name])) {
                throw InvalidCronConfiguration::at(
                    '<definitions>',
                    'profiles.' . $this->options->profile->name . '.tasks.' . $definition->name,
                    'task name collides with an already registered task.',
                );
            }

            $compiled[$definition->name] = $compiler->compile($definition, $this->options->profile->name);
        }

        $this->tasks += $compiled;

        return $this;
    }

    /** Runs the PCNTL master loop until graceful shutdown completes. */
    public function run(): void
    {
        $tasks = [];

        foreach ($this->tasks as $name => $task) {
            $task = $task instanceof ScheduledTaskBuilder ? $task->internalTask() : $task;

            if (!$task instanceof ScheduledTask) {
                throw new \LogicException('Scheduled task builder returned an invalid internal value.');
            }

            if ($task->runsIn($this->options->profile)) {
                $tasks[$name] = $task;
            }
        }

        if ($tasks === []) {
            throw InvalidCronConfiguration::at(
                '<scheduler>',
                'profiles.' . $this->options->profile->name,
                'at least one scheduled task is required for the active profile.',
            );
        }

        (new PcntlSchedulerRuntime($this->logger, $this->options))->run($tasks);
    }

    private function guardUnique(string $name): void
    {
        if (trim($name) === '') {
            throw InvalidCronConfiguration::at('<manual>', '$.tasks', 'task name must not be empty.');
        }

        if (isset($this->tasks[$name])) {
            throw InvalidCronConfiguration::at('<manual>', '$.tasks.' . $name, 'task name is already registered.');
        }
    }

    private static function guardService(string $serviceId, ?string $method, string $path): void
    {
        if (trim($serviceId) === '') {
            throw InvalidCronConfiguration::at('<manual>', $path, 'service id must not be empty.');
        }

        if ($method === '') {
            throw InvalidCronConfiguration::at('<manual>', $path, 'service method must not be empty.');
        }
    }

    /**
     * @param callable(): ContainerInterface $factory
     * @return \Closure(): ContainerInterface
     */
    private static function normalizeContainerFactory(callable $factory): \Closure
    {
        $factory = \Closure::fromCallable($factory);
        $reflection = new \ReflectionFunction($factory);

        if ($reflection->getNumberOfRequiredParameters() !== 0) {
            throw InvalidCronConfiguration::at('<scheduler>', '$.container_factory', 'container factory must not require arguments.');
        }

        return static fn(): ContainerInterface => $factory();
    }
}
