<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Task\Handler\StaticTaskHandler;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;
use Wolfcharaa\Cron\Task\OverlapPolicy;

final readonly class StaticTaskConfig implements TaskConfigInterface
{
    /** @var list<mixed> */
    public array $arguments;

    /** @param array<int|string, mixed> $arguments */
    public function __construct(
        public string $class,
        public string $method,
        array $arguments = [],
        public ?string $taskName = null,
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ) {
        if ($this->class === '') {
            throw new \InvalidArgumentException('Cron static task class must not be empty.');
        }

        if ($this->method === '') {
            throw new \InvalidArgumentException('Cron static task method must not be empty.');
        }

        if (!array_is_list($arguments)) {
            throw new \InvalidArgumentException('Cron static task arguments must be a list.');
        }

        $this->arguments = $arguments;

        if ($this->taskName === '') {
            throw new \InvalidArgumentException('Cron task name must not be empty.');
        }
    }

    public function name(): string
    {
        return $this->taskName ?? $this->class . '::' . $this->method;
    }

    public function overlapPolicy(): OverlapPolicy
    {
        return $this->overlapPolicy;
    }

    public function createHandler(?ContainerFactory $containerFactory): TaskHandler
    {
        return new StaticTaskHandler($this->class, $this->method, $this->arguments);
    }
}
