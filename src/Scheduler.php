<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wolfcharaa\Cron\Clock\SystemClock;
use Wolfcharaa\Cron\Exception\DuplicateTask;
use Wolfcharaa\Cron\Process\PcntlProcessSupervisor;
use Wolfcharaa\Cron\Process\ProcessSupervisor;
use Wolfcharaa\Cron\Runtime\RevoltSchedulerRuntime;
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Schedule\CronSchedule;
use Wolfcharaa\Cron\Task\OverlapPolicy;
use Wolfcharaa\Cron\Task\ScheduledTask;

final class Scheduler
{
    /** @var array<string, ScheduledTask> */
    private array $tasks = [];

    private readonly RevoltSchedulerRuntime $runtime;

    public function __construct(
        ?SchedulerOptions $options = null,
        ?ClockInterface $clock = null,
        ?LoggerInterface $logger = null,
        ?ProcessSupervisor $supervisor = null,
    ) {
        $this->runtime = new RevoltSchedulerRuntime(
            supervisor: $supervisor ?? new PcntlProcessSupervisor(),
            clock: $clock ?? new SystemClock(),
            logger: $logger ?? new NullLogger(),
            options: $options ?? new SchedulerOptions(),
        );
    }

    /** @param callable(): mixed $handler */
    public function cron(
        string $name,
        string $expression,
        callable $handler,
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
        $this->runtime->run($this->tasks);
    }
}
