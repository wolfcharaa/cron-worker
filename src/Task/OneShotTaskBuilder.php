<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Exception\MissingContainerFactory;
use Wolfcharaa\Cron\Task\Handler\ContainerCallbackHandler;
use Wolfcharaa\Cron\Task\Handler\ContainerServiceHandler;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;

final class OneShotTaskBuilder
{
    /** @var (\Closure(): mixed)|TaskHandler */
    private readonly \Closure|TaskHandler $handler;

    private OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid;

    private ?self $next = null;

    /** @param (callable(): mixed)|TaskHandler $handler */
    public function __construct(
        public readonly string $name,
        callable|TaskHandler $handler,
        private readonly ?ContainerFactory $containerFactory = null,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Task name must not be empty.');
        }

        $this->handler = $handler instanceof TaskHandler ? $handler : \Closure::fromCallable($handler);
    }

    /** @param (callable(): mixed)|TaskHandler $handler */
    public function then(string $name, callable|TaskHandler $handler): self
    {
        $this->next = new self($name, $handler, $this->containerFactory);

        return $this->next;
    }

    /** @param callable(ContainerInterface, TaskExecutionContext): mixed $handler */
    public function thenContainer(string $name, callable $handler): self
    {
        return $this->then($name, new ContainerCallbackHandler($this->requireContainerFactory(), $handler));
    }

    /** @param array<int|string, mixed> $arguments */
    public function thenService(
        string $name,
        string $serviceId,
        ?string $method = null,
        array $arguments = [],
    ): self {
        return $this->then($name, new ContainerServiceHandler(
            containerFactory: $this->requireContainerFactory(),
            serviceId: $serviceId,
            method: $method,
            arguments: $arguments,
        ));
    }

    public function allowOverlapping(): self
    {
        $this->overlapPolicy = OverlapPolicy::Allow;

        return $this;
    }

    public function withoutOverlapping(): self
    {
        $this->overlapPolicy = OverlapPolicy::Forbid;

        return $this;
    }

    public function build(): OneShotTask
    {
        return new OneShotTask(
            name: $this->name,
            handler: $this->handler,
            overlapPolicy: $this->overlapPolicy,
            next: $this->next?->build(),
        );
    }

    private function requireContainerFactory(): ContainerFactory
    {
        return $this->containerFactory ?? throw new MissingContainerFactory();
    }
}
