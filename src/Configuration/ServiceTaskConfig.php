<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Exception\MissingContainerFactory;
use Wolfcharaa\Cron\Task\Handler\ContainerServiceHandler;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;
use Wolfcharaa\Cron\Task\OverlapPolicy;

final readonly class ServiceTaskConfig implements TaskConfigInterface
{
    /** @param array<int|string, mixed> $arguments */
    public function __construct(
        public string $serviceId,
        public ?string $method = null,
        public array $arguments = [],
        public ?string $taskName = null,
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ) {
        if ($this->serviceId === '') {
            throw new \InvalidArgumentException('Cron service id must not be empty.');
        }

        if ($this->method === '') {
            throw new \InvalidArgumentException('Cron service method must not be empty.');
        }

        if ($this->taskName === '') {
            throw new \InvalidArgumentException('Cron task name must not be empty.');
        }
    }

    public function name(): string
    {
        return $this->taskName ?? $this->serviceId;
    }

    public function overlapPolicy(): OverlapPolicy
    {
        return $this->overlapPolicy;
    }

    public function createHandler(?ContainerFactory $containerFactory): TaskHandler
    {
        if ($containerFactory === null) {
            throw new MissingContainerFactory();
        }

        return new ContainerServiceHandler(
            containerFactory: $containerFactory,
            serviceId: $this->serviceId,
            method: $this->method,
            arguments: $this->arguments,
        );
    }
}
