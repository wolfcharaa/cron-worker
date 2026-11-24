<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Process;

use Wolfcharaa\Cron\Internal\Task\Task;

/** @internal */
final readonly class ChildProcess
{
    public function __construct(
        public int $pid,
        public Task $task,
        public \DateTimeImmutable $plannedAt,
        public \DateTimeImmutable $startedAt,
    ) {}

    public function taskName(): string
    {
        return $this->task->name;
    }
}
