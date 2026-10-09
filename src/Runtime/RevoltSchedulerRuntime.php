<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Runtime;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Wolfcharaa\Cron\Process\CompletedProcess;
use Wolfcharaa\Cron\Process\ProcessSupervisor;
use Wolfcharaa\Cron\Task\OneShotTask;
use Wolfcharaa\Cron\Task\OverlapPolicy;
use Wolfcharaa\Cron\Task\ScheduledTask;
use Wolfcharaa\Cron\Task\Task;

final class RevoltSchedulerRuntime
{
    /** @var array<string, ScheduledTask> */
    private array $tasks = [];

    /** @var array<string, \DateTimeImmutable> */
    private array $nextRuns = [];

    /** @var list<array{task: OneShotTask, queuedAt: \DateTimeImmutable}> */
    private array $pendingTasks = [];

    private ?string $scheduleTimerId = null;

    private ?string $shutdownTimerId = null;

    private ?string $childSignalId = null;

    private ?string $interruptSignalId = null;

    private ?string $terminateSignalId = null;

    private bool $started = false;

    private bool $stopping = false;

    public function __construct(
        private readonly ProcessSupervisor $supervisor,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly SchedulerOptions $options = new SchedulerOptions(),
    ) {}

    /** @param iterable<ScheduledTask> $tasks */
    public function run(iterable $tasks): void
    {
        if ($this->started) {
            throw new \LogicException('A scheduler runtime can only be started once.');
        }

        $this->started = true;
        $this->tasks = $this->indexTasks($tasks);

        if ($this->tasks === []) {
            throw new \LogicException('At least one scheduled task is required.');
        }

        $this->registerSignals();
        $now = $this->clock->now();

        foreach ($this->tasks as $name => $task) {
            $this->nextRuns[$name] = $task->schedule->nextRunAfter($now);
        }

        $this->armNextTimer();

        try {
            EventLoop::run();
        } finally {
            $this->cancelWatchers();
        }
    }

    private function registerSignals(): void
    {
        $childSignalId = EventLoop::onSignal(SIGCHLD, fn() => $this->reapChildren());
        $interruptSignalId = EventLoop::onSignal(SIGINT, fn() => $this->requestShutdown(SIGINT));
        $terminateSignalId = EventLoop::onSignal(SIGTERM, fn() => $this->requestShutdown(SIGTERM));

        $this->childSignalId = $childSignalId;
        $this->interruptSignalId = $interruptSignalId;
        $this->terminateSignalId = $terminateSignalId;

        EventLoop::unreference($childSignalId);
        EventLoop::unreference($interruptSignalId);
        EventLoop::unreference($terminateSignalId);
    }

    private function tick(): void
    {
        $this->scheduleTimerId = null;

        if ($this->stopping) {
            return;
        }

        $now = $this->clock->now();

        foreach ($this->nextRuns as $name => $plannedAt) {
            if ($plannedAt > $now) {
                continue;
            }

            $task = $this->tasks[$name] ?? null;

            if ($task === null) {
                continue;
            }

            $this->nextRuns[$name] = $task->schedule->nextRunAfter($now);

            if ($this->supervisor->runningCount() >= $this->options->maxConcurrency) {
                $this->logger->warning('Scheduled task skipped because the worker limit is reached.', [
                    'task' => $name,
                    'planned_at' => $plannedAt->format(DATE_ATOM),
                ]);

                continue;
            }

            if (
                $task->overlapPolicy === OverlapPolicy::Forbid
                && $this->supervisor->isTaskRunning($name)
            ) {
                $this->logger->warning('Overlapping scheduled task skipped.', [
                    'task' => $name,
                    'planned_at' => $plannedAt->format(DATE_ATOM),
                ]);

                continue;
            }

            $this->launchTask($task, $plannedAt, $now, 'schedule');
        }

        $this->dispatchPendingTasks();
        $this->armNextTimer();
    }

    private function armNextTimer(): void
    {
        if ($this->stopping) {
            return;
        }

        if ($this->scheduleTimerId !== null) {
            EventLoop::cancel($this->scheduleTimerId);
        }

        if ($this->nextRuns === []) {
            throw new \LogicException('No future task run is available.');
        }

        $nextRun = min($this->nextRuns);
        $now = $this->clock->now();
        $secondsUntilNextRun = (float) $nextRun->format('U.u') - (float) $now->format('U.u');
        $delay = max(0.0, min($secondsUntilNextRun, $this->options->clockResyncIntervalSeconds));

        $this->scheduleTimerId = EventLoop::delay($delay, fn() => $this->tick());
    }

    private function reapChildren(): void
    {
        foreach ($this->supervisor->reapExited() as $completed) {
            $this->logCompletion($completed);

            $next = $completed->process->task->next;

            if ($completed->succeeded() && !$this->stopping && $next !== null) {
                $this->pendingTasks[] = [
                    'task' => $next,
                    'queuedAt' => $this->clock->now(),
                ];

                $this->logger->info('Follow-up task queued.', [
                    'task' => $next->name,
                    'after' => $completed->process->taskName,
                ]);
            }
        }

        $this->dispatchPendingTasks();

        if ($this->stopping && $this->supervisor->runningCount() === 0) {
            $this->finishShutdown();
        }
    }

    private function logCompletion(CompletedProcess $completed): void
    {
        $context = [
            'task' => $completed->process->taskName,
            'pid' => $completed->process->pid,
            'exit_code' => $completed->exitCode,
            'term_signal' => $completed->termSignal,
        ];

        if ($completed->succeeded()) {
            $this->logger->info('Task completed.', $context);

            return;
        }

        $this->logger->error('Task failed.', $context);
    }

    private function launchTask(
        Task $task,
        \DateTimeImmutable $plannedAt,
        \DateTimeImmutable $startedAt,
        string $trigger,
    ): bool {
        try {
            $process = $this->supervisor->launch($task, $plannedAt, $startedAt);
            $this->logger->info('Task started.', [
                'task' => $task->name,
                'pid' => $process->pid,
                'planned_at' => $plannedAt->format(DATE_ATOM),
                'trigger' => $trigger,
            ]);

            return true;
        } catch (\Throwable $throwable) {
            $this->logger->error('Unable to start task.', [
                'task' => $task->name,
                'trigger' => $trigger,
                'exception' => $throwable,
            ]);

            return false;
        }
    }

    private function dispatchPendingTasks(): void
    {
        if ($this->stopping || $this->pendingTasks === []) {
            return;
        }

        $remaining = [];

        foreach ($this->pendingTasks as $pending) {
            $task = $pending['task'];

            if ($this->supervisor->runningCount() >= $this->options->maxConcurrency) {
                $remaining[] = $pending;

                continue;
            }

            if (
                $task->overlapPolicy === OverlapPolicy::Forbid
                && $this->supervisor->isTaskRunning($task->name)
            ) {
                $remaining[] = $pending;

                continue;
            }

            $this->launchTask($task, $pending['queuedAt'], $this->clock->now(), 'follow-up');
        }

        $this->pendingTasks = $remaining;
    }

    private function requestShutdown(int $signal): void
    {
        if ($this->stopping) {
            $this->supervisor->signalAll(SIGKILL);

            return;
        }

        $this->stopping = true;
        $this->pendingTasks = [];
        $this->logger->info('Scheduler shutdown requested.', ['signal' => $signal]);

        if ($this->scheduleTimerId !== null) {
            EventLoop::cancel($this->scheduleTimerId);
            $this->scheduleTimerId = null;
        }

        if ($this->supervisor->runningCount() === 0) {
            $this->finishShutdown();

            return;
        }

        if ($this->childSignalId !== null) {
            EventLoop::reference($this->childSignalId);
        }

        $this->supervisor->signalAll(SIGTERM);
        $this->shutdownTimerId = EventLoop::delay(
            $this->options->shutdownGracePeriodSeconds,
            function (): void {
                $this->shutdownTimerId = null;
                $this->supervisor->signalAll(SIGKILL);
            },
        );
    }

    private function finishShutdown(): void
    {
        if ($this->shutdownTimerId !== null) {
            EventLoop::cancel($this->shutdownTimerId);
            $this->shutdownTimerId = null;
        }

        $this->cancelWatchers();
    }

    private function cancelWatchers(): void
    {
        foreach ([
            $this->scheduleTimerId,
            $this->shutdownTimerId,
            $this->childSignalId,
            $this->interruptSignalId,
            $this->terminateSignalId,
        ] as $callbackId) {
            if ($callbackId !== null) {
                EventLoop::cancel($callbackId);
            }
        }

        $this->scheduleTimerId = null;
        $this->shutdownTimerId = null;
        $this->childSignalId = null;
        $this->interruptSignalId = null;
        $this->terminateSignalId = null;
    }

    /**
     * @param iterable<ScheduledTask> $tasks
     *
     * @return array<string, ScheduledTask>
     */
    private function indexTasks(iterable $tasks): array
    {
        $indexed = [];

        foreach ($tasks as $task) {
            if (isset($indexed[$task->name])) {
                throw new \LogicException(\sprintf('Duplicate task name "%s".', $task->name));
            }

            $indexed[$task->name] = $task;
        }

        return $indexed;
    }
}
