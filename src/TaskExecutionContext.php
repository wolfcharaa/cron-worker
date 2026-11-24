<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron;

/** Immutable timing data supplied to context-aware manual callables. */
final readonly class TaskExecutionContext
{
    /** Creates handler timing data; application code normally receives this from the scheduler. */
    public function __construct(
        public string $taskName,
        public \DateTimeImmutable $plannedAt,
        public \DateTimeImmutable $startedAt,
    ) {
        if (trim($this->taskName) === '') {
            throw new \InvalidArgumentException('Task name must not be empty.');
        }
    }
}
