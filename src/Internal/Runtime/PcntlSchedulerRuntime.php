<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Runtime;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Wolfcharaa\Cron\Exception\CronRuntimeException;
use Wolfcharaa\Cron\Internal\Clock\SystemClock;
use Wolfcharaa\Cron\Internal\Process\CompletedProcess;
use Wolfcharaa\Cron\Internal\Process\PcntlProcessSupervisor;
use Wolfcharaa\Cron\Internal\Task\ScheduledTask;
use Wolfcharaa\Cron\Internal\Task\Task;
use Wolfcharaa\Cron\SchedulerOptions;
use Wolfcharaa\Cron\Task\OverlapPolicy;

/** @internal Sequential PCNTL scheduler loop. */
final class PcntlSchedulerRuntime
{
    private const MAX_WAIT_MICROSECONDS = 250_000;

    /** @var array<string, ScheduledTask> */
    private array $tasks = [];

    /** @var array<string, \DateTimeImmutable> */
    private array $nextRuns = [];

    /** @var list<array{task: Task, queuedAt: \DateTimeImmutable}> */
    private array $pending = [];

    private int $shutdownRequests = 0;

    private bool $childSignal = false;

    private bool $started = false;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SchedulerOptions $options,
        private readonly PcntlProcessSupervisor $supervisor = new PcntlProcessSupervisor(),
        private readonly ClockInterface $clock = new SystemClock(),
    ) {}

    /** @param iterable<ScheduledTask> $tasks */
    public function run(iterable $tasks): void
    {
        if ($this->started) {
            throw new CronRuntimeException('A scheduler runtime can only be started once.');
        }

        $this->started = true;

        foreach ($tasks as $task) {
            $this->tasks[$task->task->name] = $task;
        }

        if ($this->tasks === []) {
            throw new CronRuntimeException('At least one scheduled task is required.');
        }

        $now = $this->now();

        foreach ($this->tasks as $name => $task) {
            $this->nextRuns[$name] = $task->nextRunAfter($now);
        }

        $signals = $this->installSignals();

        try {
            $this->loop();
        } catch (CronRuntimeException $exception) {
            $this->emergencyStop();

            throw $exception;
        } catch (\Throwable $throwable) {
            $this->emergencyStop();

            throw new CronRuntimeException('Scheduler runtime failed.', 0, $throwable);
        } finally {
            $this->restoreSignals($signals);
        }
    }

    private function loop(): void
    {
        $stopping = false;
        $killSent = false;
        $shutdownDeadline = 0;

        while (true) {
            $this->reapChildren($stopping);

            if ($this->shutdownRequests > 0 && !$stopping) {
                $stopping = true;
                $shutdownDeadline = $this->monotonicNanoseconds() + (int) round($this->options->shutdownGracePeriodSeconds * 1_000_000_000);
                $this->logger->notice('Scheduler shutdown requested; terminating child process groups.');
                $this->supervisor->signalAll(SIGTERM);
            }

            if ($stopping) {
                if ($this->supervisor->runningCount() === 0) {
                    return;
                }

                if (!$killSent && ($this->shutdownRequests > 1 || $this->monotonicNanoseconds() >= $shutdownDeadline)) {
                    $killSent = true;
                    $this->logger->warning('Scheduler shutdown grace expired; killing child process groups.');
                    $this->supervisor->signalAll(SIGKILL);
                }

                $this->wait($killSent ? self::MAX_WAIT_MICROSECONDS : $this->remainingGraceMicros($shutdownDeadline));

                continue;
            }

            $this->dispatchScheduledTasks();
            $this->dispatchPendingTasks();
            $this->wait($this->nextWaitMicroseconds());
        }
    }

    private function dispatchScheduledTasks(): void
    {
        $now = $this->now();

        foreach ($this->nextRuns as $name => $plannedAt) {
            if ($plannedAt > $now) {
                continue;
            }

            $scheduled = $this->tasks[$name] ?? throw new CronRuntimeException(
                \sprintf('Scheduled task "%s" disappeared from runtime state.', $name),
            );
            $this->nextRuns[$name] = $scheduled->nextRunAfter($now);

            if ($this->supervisor->runningCount() >= $this->options->maxConcurrency) {
                $this->logger->warning('Scheduled task skipped because the worker limit is reached.', [
                    'task' => $name,
                    'planned_at' => $plannedAt->format(DATE_ATOM),
                ]);

                continue;
            }

            if ($scheduled->overlapPolicy() === OverlapPolicy::Forbid && $this->supervisor->isTaskRunning($name)) {
                $this->logger->warning('Overlapping scheduled task skipped.', [
                    'task' => $name,
                    'planned_at' => $plannedAt->format(DATE_ATOM),
                ]);

                continue;
            }

            $this->launch($scheduled->task, $plannedAt, $now, 'schedule');
        }
    }

    private function reapChildren(bool $stopping): void
    {
        $this->childSignal = false;

        foreach ($this->supervisor->reapExited() as $completed) {
            $this->logCompletion($completed);
            $next = $completed->process->task->next;

            if ($completed->succeeded() && !$stopping && $next !== null) {
                $this->pending[] = ['task' => $next, 'queuedAt' => $this->now()];
                $this->logger->info('Follow-up task queued.', [
                    'task' => $next->name,
                    'after' => $completed->process->taskName(),
                ]);
            }
        }
    }

    private function dispatchPendingTasks(): void
    {
        $remaining = [];

        foreach ($this->pending as $pending) {
            $task = $pending['task'];

            if ($this->supervisor->runningCount() >= $this->options->maxConcurrency) {
                $remaining[] = $pending;

                continue;
            }

            if ($task->overlapPolicy === OverlapPolicy::Forbid && $this->supervisor->isTaskRunning($task->name)) {
                $remaining[] = $pending;

                continue;
            }

            $now = $this->now();
            $this->launch($task, $pending['queuedAt'], $now, 'chain');
        }

        $this->pending = $remaining;
    }

    private function launch(Task $task, \DateTimeImmutable $plannedAt, \DateTimeImmutable $startedAt, string $trigger): void
    {
        $process = $this->supervisor->launch($task, $plannedAt, $startedAt);
        $this->logger->info('Task started.', [
            'task' => $task->name,
            'pid' => $process->pid,
            'planned_at' => $plannedAt->format(DATE_ATOM),
            'trigger' => $trigger,
        ]);
    }

    private function logCompletion(CompletedProcess $completed): void
    {
        $context = [
            'task' => $completed->process->taskName(),
            'pid' => $completed->process->pid,
            'exit_code' => $completed->exitCode,
            'term_signal' => $completed->termSignal,
        ];

        if ($completed->succeeded()) {
            $this->logger->info('Task completed.', $context);
        } else {
            $this->logger->error('Task failed.', $context);
        }
    }

    private function nextWaitMicroseconds(): int
    {
        if ($this->childSignal || $this->pending !== []) {
            return 0;
        }

        if ($this->nextRuns === []) {
            throw new CronRuntimeException('No future task run is available.');
        }

        $next = min($this->nextRuns);
        $seconds = (float) $next->format('U.u') - (float) $this->now()->format('U.u');

        return max(0, min(self::MAX_WAIT_MICROSECONDS, (int) round($seconds * 1_000_000)));
    }

    private function remainingGraceMicros(int $deadline): int
    {
        return max(0, min(
            self::MAX_WAIT_MICROSECONDS,
            intdiv(max(0, $deadline - $this->monotonicNanoseconds()), 1_000),
        ));
    }

    private function wait(int $microseconds): void
    {
        if ($microseconds > 0) {
            usleep($microseconds);
        }
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->options->timezone);
    }

    /** @return array{async: bool, handlers: array<int, mixed>} */
    private function installSignals(): array
    {
        $handlers = [];

        foreach ([SIGCHLD, SIGINT, SIGTERM] as $signal) {
            $handlers[$signal] = pcntl_signal_get_handler($signal);
        }

        $async = pcntl_async_signals();
        pcntl_async_signals(true);
        pcntl_signal(SIGCHLD, function (): void {
            $this->childSignal = true;
        });
        pcntl_signal(SIGINT, function (): void {
            ++$this->shutdownRequests;
        });
        pcntl_signal(SIGTERM, function (): void {
            ++$this->shutdownRequests;
        });

        return ['async' => $async, 'handlers' => $handlers];
    }

    /** @param array{async: bool, handlers: array<int, mixed>} $signals */
    private function restoreSignals(array $signals): void
    {
        foreach ($signals['handlers'] as $signal => $handler) {
            if (!\is_int($handler) && !\is_callable($handler)) {
                throw new CronRuntimeException(\sprintf('Signal %d has an invalid previous handler.', $signal));
            }

            pcntl_signal($signal, $handler);
        }

        pcntl_async_signals($signals['async']);
    }

    private function emergencyStop(): void
    {
        if ($this->supervisor->runningCount() === 0) {
            return;
        }

        try {
            $this->supervisor->signalAll(SIGKILL);
        } catch (\Throwable) {
            // Preserve the original fatal runtime failure.
        }
    }

    private function monotonicNanoseconds(): int
    {
        $value = hrtime(true);

        if (!\is_int($value)) {
            throw new CronRuntimeException('The cron runtime requires a 64-bit monotonic integer clock.');
        }

        return $value;
    }
}
