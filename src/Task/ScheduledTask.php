<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

use Wolfcharaa\Cron\Exception\InvalidTaskResult;
use Wolfcharaa\Cron\Schedule\Schedule;

final readonly class ScheduledTask
{
    /** @var \Closure(): mixed */
    private \Closure $handler;

    /** @param callable(): mixed $handler */
    public function __construct(
        public string $name,
        public Schedule $schedule,
        callable $handler,
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Task name must not be empty.');
        }

        $this->handler = \Closure::fromCallable($handler);
    }

    public function execute(): int
    {
        $result = ($this->handler)();

        if ($result === null) {
            return 0;
        }

        if (\is_int($result) && $result >= 0 && $result <= 255) {
            return $result;
        }

        throw new InvalidTaskResult($this->name, $result);
    }
}
