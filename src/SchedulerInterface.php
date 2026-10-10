<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Configuration\Loader\CronConfigLoaderInterface;
use Wolfcharaa\Cron\Registry\CronProfileMap;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;
use Wolfcharaa\Cron\Task\OverlapPolicy;
use Wolfcharaa\Cron\Task\ScheduledTask;
use Wolfcharaa\Cron\Task\ScheduledTaskBuilder;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

interface SchedulerInterface
{
    /** @param (callable(): mixed)|TaskHandler $handler */
    public function task(string $name, callable|TaskHandler $handler): ScheduledTaskBuilder;

    /**
     * @param callable(ContainerInterface, TaskExecutionContext, mixed...): mixed $handler
     * @param list<mixed> $arguments
     */
    public function containerTask(string $name, callable $handler, array $arguments = []): ScheduledTaskBuilder;

    /** @param list<mixed> $arguments */
    public function staticTask(
        string $name,
        string $class,
        string $method,
        array $arguments = [],
    ): ScheduledTaskBuilder;

    /** @param array<int|string, mixed> $arguments */
    public function serviceTask(
        string $name,
        string $serviceId,
        ?string $method = null,
        array $arguments = [],
    ): ScheduledTaskBuilder;

    /** @param (callable(): mixed)|TaskHandler $handler */
    public function cron(
        string $name,
        CronExpression|string $expression,
        callable|TaskHandler $handler,
        OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ): self;

    public function add(ScheduledTask $task): self;

    public function load(CronProfileMap|CronConfigLoaderInterface $profiles): self;

    /** @return array<string, ScheduledTask> */
    public function tasks(): array;

    public function run(): void;
}
