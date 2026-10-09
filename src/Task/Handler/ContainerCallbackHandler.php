<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task\Handler;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Task\TaskExecutionContext;
use Wolfcharaa\Cron\Validation\CallableTaskValidator;

final readonly class ContainerCallbackHandler implements TaskHandler
{
    /** @var \Closure(ContainerInterface, TaskExecutionContext, mixed...): mixed */
    private \Closure $handler;

    /** @var list<mixed> */
    private array $arguments;

    /**
     * @param callable(ContainerInterface, TaskExecutionContext, mixed...): mixed $handler
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(
        private ContainerFactory $containerFactory,
        callable $handler,
        array $arguments = [],
    ) {
        if (!array_is_list($arguments)) {
            throw new \InvalidArgumentException('Container callback arguments must be a list.');
        }

        $this->arguments = $arguments;
        (new CallableTaskValidator())->validateContainerCallable($handler, $this->arguments);
        $this->handler = \Closure::fromCallable($handler);
    }

    public function execute(TaskExecutionContext $context): mixed
    {
        return ($this->handler)($this->containerFactory->create(), $context, ...$this->arguments);
    }
}
