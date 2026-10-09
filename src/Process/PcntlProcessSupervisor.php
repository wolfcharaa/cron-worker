<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Process;

use Wolfcharaa\Cron\Exception\ProcessControlFailure;
use Wolfcharaa\Cron\Task\Task;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

final class PcntlProcessSupervisor implements ProcessSupervisor
{
    /** @var array<int, ChildProcess> */
    private array $children = [];

    public function launch(
        Task $task,
        \DateTimeImmutable $plannedAt,
        \DateTimeImmutable $startedAt,
    ): ChildProcess {
        $previousMask = [];

        if (!pcntl_sigprocmask(SIG_BLOCK, [SIGCHLD], $previousMask)) {
            throw new ProcessControlFailure('Unable to block SIGCHLD before forking.');
        }

        $validatedMask = $this->validateSignalMask($previousMask);

        try {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new ProcessControlFailure('Unable to fork a task process.');
            }

            if ($pid === 0) {
                $this->runChild($task, $plannedAt, $startedAt, $validatedMask);
            }

            $process = new ChildProcess(
                pid: $pid,
                task: $task,
                plannedAt: $plannedAt,
                startedAt: $startedAt,
            );
            $this->children[$pid] = $process;

            // The child performs the same call before executing user code. Doing
            // it here as well closes the short race with immediate shutdown.
            @posix_setpgid($pid, $pid);

            return $process;
        } finally {
            if (!pcntl_sigprocmask(SIG_SETMASK, $validatedMask)) {
                throw new ProcessControlFailure('Unable to restore the parent signal mask.');
            }
        }
    }

    public function reapExited(): array
    {
        $completed = [];

        while (true) {
            $status = 0;
            $pid = pcntl_waitpid(-1, $status, WNOHANG);

            if ($pid <= 0) {
                break;
            }

            if (!\is_int($status)) {
                throw new ProcessControlFailure('PCNTL returned an invalid child status.');
            }

            $process = $this->children[$pid] ?? null;
            unset($this->children[$pid]);

            if ($process === null) {
                continue;
            }

            $exitCode = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : null;
            $termSignal = pcntl_wifsignaled($status) ? pcntl_wtermsig($status) : null;

            $completed[] = new CompletedProcess(
                process: $process,
                exitCode: $exitCode === false ? null : $exitCode,
                termSignal: $termSignal === false ? null : $termSignal,
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
            if ($process->taskName === $taskName) {
                return true;
            }
        }

        return false;
    }

    public function signalAll(int $signal): void
    {
        foreach ($this->children as $process) {
            if (!@posix_kill(-$process->pid, $signal)) {
                @posix_kill($process->pid, $signal);
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
        pcntl_sigprocmask(SIG_SETMASK, $previousMask);

        foreach ([SIGCHLD, SIGINT, SIGTERM, SIGHUP] as $signal) {
            pcntl_signal($signal, SIG_DFL);
        }

        posix_setpgid(0, 0);

        try {
            exit($task->execute(new TaskExecutionContext(
                taskName: $task->name,
                plannedAt: $plannedAt,
                startedAt: $startedAt,
            )));
        } catch (\Throwable $throwable) {
            fwrite(
                STDERR,
                \sprintf(
                    "Task \"%s\" failed: %s in %s:%d\n",
                    $task->name,
                    $throwable->getMessage(),
                    $throwable->getFile(),
                    $throwable->getLine(),
                ),
            );

            exit(255);
        }
    }

    /** @return list<int> */
    private function validateSignalMask(mixed $mask): array
    {
        if (!\is_array($mask)) {
            throw new ProcessControlFailure('PCNTL returned an invalid signal mask.');
        }

        $validated = [];

        foreach ($mask as $signal) {
            if (!\is_int($signal)) {
                throw new ProcessControlFailure('PCNTL returned an invalid signal number.');
            }

            $validated[] = $signal;
        }

        return $validated;
    }
}
