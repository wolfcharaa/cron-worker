<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron;

use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wolfcharaa\Cron\Clock\SystemClock;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Container\CallableContainerFactory;
use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Exception\DuplicateTask;
use Wolfcharaa\Cron\Exception\MissingContainerFactory;
use Wolfcharaa\Cron\Process\PcntlProcessSupervisor;
use Wolfcharaa\Cron\Process\ProcessSupervisor;
use Wolfcharaa\Cron\Runtime\RevoltSchedulerRuntime;
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Schedule\CronSchedule;
use Wolfcharaa\Cron\Task\Handler\ContainerCallbackHandler;
use Wolfcharaa\Cron\Task\Handler\ContainerServiceHandler;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;
use Wolfcharaa\Cron\Task\OverlapPolicy;
use Wolfcharaa\Cron\Task\ScheduledTask;
use Wolfcharaa\Cron\Task\ScheduledTaskBuilder;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

final class Scheduler implements SchedulerInterface
{
    /** @var array<string, ScheduledTask|ScheduledTaskBuilder> */
    private array $tasks = [];

    private readonly RevoltSchedulerRuntime $runtime;

    private readonly ?ContainerFactory $containerFactory;

    private readonly Profile $profile;

    /** @param ContainerFactory|(callable(): ContainerInterface)|null $containerFactory */
    public function __construct(
        ?SchedulerOptions $options = null,
        ?ClockInterface $clock = null,
        ?LoggerInterface $logger = null,
        ?ProcessSupervisor $supervisor = null,
        null|ContainerFactory|callable $containerFactory = null,
    ) {
        $options ??= new SchedulerOptions();
        $this->profile = $options->profile;
        $this->containerFactory = match (true) {
            $containerFactory instanceof ContainerFactory => $containerFactory,
            $containerFactory !== null => new CallableContainerFactory($containerFactory),
            default => null,
        };
        $this->runtime = new RevoltSchedulerRuntime(
            supervisor: $supervisor ?? new PcntlProcessSupervisor(),
            clock: $clock ?? new SystemClock(),
            logger: $logger ?? new NullLogger(),
            options: $options,
        );
    }

    /** @param (callable(): mixed)|TaskHandler $handler */
    public function task(string $name, callable|TaskHandler $handler): ScheduledTaskBuilder
    {
        if (isset($this->tasks[$name])) {
            throw new DuplicateTask($name);
        }

        $builder = new ScheduledTaskBuilder(
            name: $name,
            handler: $handler,
            containerFactory: $this->containerFactory,
        );
        $this->tasks[$name] = $builder;

        return $builder;
    }

    /** @param callable(ContainerInterface, TaskExecutionContext): mixed $handler */
    public function containerTask(string $name, callable $handler): ScheduledTaskBuilder
    {
        return $this->task($name, new ContainerCallbackHandler($this->requireContainerFactory(), $handler));
    }

    /** @param array<int|string, mixed> $arguments */
    public function serviceTask(
        string $name,
        string $serviceId,
        ?string $method = null,
        array $arguments = [],
    ): ScheduledTaskBuilder {
        return $this->task($name, new ContainerServiceHandler(
            containerFactory: $this->requireContainerFactory(),
            serviceId: $serviceId,
            method: $method,
            arguments: $arguments,
        ));
    }

    /** @param (callable(): mixed)|TaskHandler $handler */
    public function cron(
        string $name,
        CronExpression|string $expression,
        callable|TaskHandler $handler,
        \DateTimeZone|string $timezone = 'UTC',
        OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ): self {
        return $this->add(new ScheduledTask(
            name: $name,
            schedule: new CronSchedule($expression, $timezone),
            handler: $handler,
            overlapPolicy: $overlapPolicy,
        ));
    }

    public function add(ScheduledTask $task): self
    {
        if (isset($this->tasks[$task->name])) {
            throw new DuplicateTask($task->name);
        }

        $this->tasks[$task->name] = $task;

        return $this;
    }

    public function run(): void
    {
        $this->runtime->run($this->tasks());
    }

    public function tasks(): array
    {
        $tasks = [];

        foreach ($this->tasks as $name => $task) {
            $task = $task instanceof ScheduledTaskBuilder ? $task->build() : $task;

            if ($task->runsIn($this->profile)) {
                $tasks[$name] = $task;
            }
        }

        if ($tasks === []) {
            throw new \LogicException(\sprintf(
                'No scheduled tasks are enabled for profile "%s".',
                $this->profile->name,
            ));
        }

        return $tasks;
    }

    private function requireContainerFactory(): ContainerFactory
    {
        return $this->containerFactory ?? throw new MissingContainerFactory();
    }
}
