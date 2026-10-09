<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task\Handler;

use Wolfcharaa\Cron\Task\TaskExecutionContext;
use Wolfcharaa\Cron\Validation\CallableTaskValidator;

final readonly class CallableTaskHandler implements TaskHandler
{
    /** @var \Closure(): mixed */
    private \Closure $handler;

    /** @param callable(): mixed $handler */
    public function __construct(callable $handler)
    {
        (new CallableTaskValidator())->validateCallable($handler);
        $this->handler = \Closure::fromCallable($handler);
    }

    public function execute(TaskExecutionContext $context): mixed
    {
        return ($this->handler)();
    }
}
