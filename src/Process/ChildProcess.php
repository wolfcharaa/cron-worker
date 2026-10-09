<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Process;

final readonly class ChildProcess
{
    public function __construct(
        public int $pid,
        public string $taskName,
        public \DateTimeImmutable $plannedAt,
        public \DateTimeImmutable $startedAt,
    ) {}
}
