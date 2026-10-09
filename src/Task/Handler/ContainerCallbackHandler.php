<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task\Handler;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

final readonly class ContainerCallbackHandler implements TaskHandler
{
    /** @var \Closure(ContainerInterface, TaskExecutionContext): mixed */
    private \Closure $handler;

    /** @param callable(ContainerInterface, TaskExecutionContext): mixed $handler */
    public function __construct(
        private ContainerFactory $containerFactory,
        callable $handler,
    ) {
        $this->handler = \Closure::fromCallable($handler);
    }

    public function execute(TaskExecutionContext $context): mixed
    {
        return ($this->handler)($this->containerFactory->create(), $context);
    }
}
