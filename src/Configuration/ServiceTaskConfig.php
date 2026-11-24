<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

use Wolfcharaa\Cron\Task\OverlapPolicy;

/** Data-only declaration of a child-local PSR-11 service task. */
final readonly class ServiceTaskConfig
{
    /** @param array<int|string, mixed> $arguments */
    public function __construct(
        public string $serviceId,
        public ?string $method = null,
        public array $arguments = [],
        public ?string $taskName = null,
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ) {
        if (trim($this->serviceId) === '') {
            throw new \InvalidArgumentException('Cron service id must not be empty.');
        }

        if ($this->method === '') {
            throw new \InvalidArgumentException('Cron service method must not be empty.');
        }

        if ($this->taskName === '') {
            throw new \InvalidArgumentException('Cron task name must not be empty.');
        }
    }

    /** Returns the stable scheduler task name. */
    public function name(): string
    {
        return $this->taskName ?? $this->serviceId;
    }

    /** Returns the task overlap policy. */
    public function overlapPolicy(): OverlapPolicy
    {
        return $this->overlapPolicy;
    }
}
