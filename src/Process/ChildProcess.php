<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Process;

use Wolfcharaa\Cron\Task\Task;

final readonly class ChildProcess
{
    public string $taskName;

    public function __construct(
        public int $pid,
        public Task $task,
        public \DateTimeImmutable $plannedAt,
        public \DateTimeImmutable $startedAt,
    ) {
        $this->taskName = $this->task->name;
    }
}
