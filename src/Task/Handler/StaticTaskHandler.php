<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task\Handler;

use Wolfcharaa\Cron\Task\TaskExecutionContext;
use Wolfcharaa\Cron\Validation\CallableTaskValidator;

final readonly class StaticTaskHandler implements TaskHandler
{
    /** @var \Closure(mixed...): mixed */
    private \Closure $handler;

    /** @param list<mixed> $arguments */
    public function __construct(
        string $class,
        string $method,
        private array $arguments = [],
    ) {
        (new CallableTaskValidator())->validateStaticMethod($class, $method, $this->arguments);
        $handler = [$class, $method];

        if (!\is_callable($handler)) {
            throw new \LogicException(\sprintf('Validated static callable "%s::%s" is unavailable.', $class, $method));
        }

        $this->handler = \Closure::fromCallable($handler);
    }

    public function execute(TaskExecutionContext $context): mixed
    {
        return ($this->handler)(...$this->arguments);
    }
}
