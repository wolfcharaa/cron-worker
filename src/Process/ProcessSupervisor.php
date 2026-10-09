<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Process;

use Wolfcharaa\Cron\Task\ScheduledTask;

interface ProcessSupervisor
{
    public function launch(ScheduledTask $task, \DateTimeImmutable $plannedAt, \DateTimeImmutable $startedAt): ChildProcess;

    /** @return list<CompletedProcess> */
    public function reapExited(): array;

    public function runningCount(): int;

    public function isTaskRunning(string $taskName): bool;

    public function signalAll(int $signal): void;
}
