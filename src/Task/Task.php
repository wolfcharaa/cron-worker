<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

use Wolfcharaa\Cron\Exception\InvalidTaskResult;
use Wolfcharaa\Cron\Task\Handler\CallableTaskHandler;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;

abstract readonly class Task
{
    private TaskHandler $handler;

    /** @param (callable(): mixed)|TaskHandler $handler */
    public function __construct(
        public string $name,
        callable|TaskHandler $handler,
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
        public ?OneShotTask $next = null,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Task name must not be empty.');
        }

        $this->handler = $handler instanceof TaskHandler ? $handler : new CallableTaskHandler($handler);
    }

    final public function execute(?TaskExecutionContext $context = null): int
    {
        if ($context === null) {
            $now = new \DateTimeImmutable();
            $context = new TaskExecutionContext($this->name, $now, $now);
        }

        $result = $this->handler->execute($context);

        if ($result === null) {
            return 0;
        }

        if (\is_int($result) && $result >= 0 && $result <= 255) {
            return $result;
        }

        throw new InvalidTaskResult($this->name, $result);
    }
}
