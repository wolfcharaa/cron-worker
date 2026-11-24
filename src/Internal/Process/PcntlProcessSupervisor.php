<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Process;

use Wolfcharaa\Cron\Exception\CronRuntimeException;
use Wolfcharaa\Cron\Internal\Task\Task;
use Wolfcharaa\Cron\TaskExecutionContext;

/** @internal */
final class PcntlProcessSupervisor
{
    /** @var array<int, ChildProcess> */
    private array $children = [];

    public function launch(
        Task $task,
        \DateTimeImmutable $plannedAt,
        \DateTimeImmutable $startedAt,
    ): ChildProcess {
        $previousMask = [];

        if (!pcntl_sigprocmask(SIG_BLOCK, [SIGCHLD], $previousMask) || !\is_array($previousMask)) {
            throw new CronRuntimeException('Unable to block SIGCHLD before forking.');
        }

        $previousMask = $this->signalMask($previousMask);

        try {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new CronRuntimeException('Unable to fork a task process.');
            }

            if ($pid === 0) {
                $this->runChild($task, $plannedAt, $startedAt, $previousMask);
            }

            $process = new ChildProcess($pid, $task, $plannedAt, $startedAt);
            $this->children[$pid] = $process;
            @posix_setpgid($pid, $pid);

            return $process;
        } finally {
            if (!pcntl_sigprocmask(SIG_SETMASK, $previousMask)) {
                throw new CronRuntimeException('Unable to restore the parent signal mask.');
            }
        }
    }

    /** @return list<CompletedProcess> */
    public function reapExited(): array
    {
        $completed = [];

        while (true) {
            $status = 0;
            $pid = pcntl_waitpid(-1, $status, WNOHANG);

            if (!\is_int($status)) {
                throw new CronRuntimeException('PCNTL returned an invalid child status.');
            }

            if ($pid === 0) {
                break;
            }

            if ($pid === -1) {
                $error = pcntl_get_last_error();

                if ($error !== PCNTL_ECHILD) {
                    throw new CronRuntimeException('Unable to reap child processes: ' . pcntl_strerror($error));
                }

                break;
            }

            $process = $this->children[$pid] ?? null;
            unset($this->children[$pid]);

            if ($process === null) {
                continue;
            }

            $exitCode = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : null;
            $termSignal = pcntl_wifsignaled($status) ? pcntl_wtermsig($status) : null;
            $completed[] = new CompletedProcess(
                $process,
                $exitCode === false ? null : $exitCode,
                $termSignal === false ? null : $termSignal,
            );
        }

        return $completed;
    }

    public function runningCount(): int
    {
        return \count($this->children);
    }

    public function isTaskRunning(string $taskName): bool
    {
        foreach ($this->children as $process) {
            if ($process->taskName() === $taskName) {
                return true;
            }
        }

        return false;
    }

    public function signalAll(int $signal): void
    {
        foreach ($this->children as $process) {
            if (!@posix_kill(-$process->pid, $signal) && !@posix_kill($process->pid, $signal)) {
                $error = posix_get_last_error();

                if ($error !== 3) {
                    throw new CronRuntimeException(\sprintf(
                        'Unable to signal child process %d: %s',
                        $process->pid,
                        posix_strerror($error),
                    ));
                }
            }
        }
    }

    /** @param list<int> $previousMask */
    private function runChild(
        Task $task,
        \DateTimeImmutable $plannedAt,
        \DateTimeImmutable $startedAt,
        array $previousMask,
    ): never {
        pcntl_async_signals(false);
        pcntl_sigprocmask(SIG_SETMASK, $previousMask);

        foreach ([SIGCHLD, SIGINT, SIGTERM, SIGHUP] as $signal) {
            pcntl_signal($signal, SIG_DFL);
        }

        @posix_setpgid(0, 0);

        try {
            exit($task->execute(new TaskExecutionContext($task->name, $plannedAt, $startedAt)));
        } catch (\Throwable $throwable) {
            fwrite(STDERR, \sprintf(
                "Task \"%s\" failed: %s in %s:%d\n",
                $task->name,
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
            ));
            exit(255);
        }
    }

    /** @return list<int> */
    private function signalMask(mixed $mask): array
    {
        if (!\is_array($mask)) {
            throw new CronRuntimeException('PCNTL returned an invalid signal mask.');
        }

        $signals = [];

        foreach ($mask as $signal) {
            if (!\is_int($signal)) {
                throw new CronRuntimeException('PCNTL returned an invalid signal number.');
            }

            $signals[] = $signal;
        }

        return $signals;
    }
}
