<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

final readonly class TaskExecutionContext
{
    public function __construct(
        public string $taskName,
        public \DateTimeImmutable $plannedAt,
        public \DateTimeImmutable $startedAt,
    ) {}
}
