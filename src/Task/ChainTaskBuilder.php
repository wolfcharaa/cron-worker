<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Internal\Task\CallableTaskHandler;
use Wolfcharaa\Cron\Internal\Task\ContainerServiceTaskHandler;
use Wolfcharaa\Cron\Internal\Task\Task;
use Wolfcharaa\Cron\Internal\Task\TaskHandler;
use Wolfcharaa\Cron\TaskExecutionContext;

/** Fluent success-chain builder returned by task registration methods. */
final class ChainTaskBuilder
{
    private OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid;

    private ?self $next = null;

    /** @param \Closure(): ContainerInterface|null $containerFactory */
    private function __construct(
        public readonly string $name,
        private readonly TaskHandler $handler,
        private readonly ?\Closure $containerFactory,
    ) {
        if (trim($this->name) === '') {
            throw InvalidCronConfiguration::at('<manual>', '$.tasks', 'task name must not be empty.');
        }
    }

    /**
     * @internal package assembly hook; not a supported consumer call
     * @param (\Closure(): ContainerInterface)|null $containerFactory
     */
    public static function assemble(string $name, mixed $handler, ?\Closure $containerFactory): self
    {
        if (!$handler instanceof TaskHandler) {
            throw new \LogicException('Chain task assembly requires an internal task handler.');
        }

        return new self($name, $handler, $containerFactory);
    }

    /**
     * Adds a callable successor that runs only after exit zero.
     *
     * @param (callable(): (int|null))|(callable(TaskExecutionContext): (int|null)) $handler
     */
    public function then(string $name, callable $handler): self
    {
        $this->next = self::assemble(
            $name,
            new CallableTaskHandler($handler, '$.tasks.' . $name),
            $this->containerFactory,
        );

        return $this->next;
    }

    /**
     * Adds a PSR-11 service successor that runs only after exit zero.
     *
     * @param array<int|string, mixed> $arguments
     */
    public function thenService(
        string $name,
        string $serviceId,
        ?string $method = null,
        array $arguments = [],
    ): self {
        if (!array_is_list($arguments)) {
            throw InvalidCronConfiguration::at('<manual>', '$.tasks.' . $name, 'service arguments must be a positional list.');
        }

        if (trim($serviceId) === '' || $method === '') {
            throw InvalidCronConfiguration::at('<manual>', '$.tasks.' . $name, 'service id and optional method must not be empty.');
        }

        $factory = $this->containerFactory ?? throw InvalidCronConfiguration::at(
            '<manual>',
            '$.tasks.' . $name,
            'a container factory is required for service tasks.',
        );

        $this->next = self::assemble(
            $name,
            new ContainerServiceTaskHandler($factory, $serviceId, $method, $arguments),
            $factory,
        );

        return $this->next;
    }

    /** Allows children with this task name to overlap. */
    public function allowOverlapping(): self
    {
        $this->overlapPolicy = OverlapPolicy::Allow;

        return $this;
    }

    /** Forbids children with this task name from overlapping. */
    public function withoutOverlapping(): self
    {
        $this->overlapPolicy = OverlapPolicy::Forbid;

        return $this;
    }

    /** @internal */
    public function internalTask(): object
    {
        $next = $this->next?->internalTask();

        if ($next !== null && !$next instanceof Task) {
            throw new \LogicException('Chain task builder returned an invalid internal value.');
        }

        return new Task(
            $this->name,
            $this->handler,
            $this->overlapPolicy,
            $next,
        );
    }
}
