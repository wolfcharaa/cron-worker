<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Task\Handler\ExecTaskHandler;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;
use Wolfcharaa\Cron\Task\OverlapPolicy;

final readonly class ExecTaskConfig implements TaskConfigInterface
{
    /** @param array<string, string> $environment */
    public function __construct(
        public string $taskName,
        public string $command,
        public ?string $workingDirectory = null,
        public array $environment = [],
        public string $shell = '/bin/sh',
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ) {
        if ($this->taskName === '') {
            throw new \InvalidArgumentException('Exec task name must not be empty.');
        }

        if ($this->command === '') {
            throw new \InvalidArgumentException('Exec command must not be empty.');
        }

        if ($this->workingDirectory === '') {
            throw new \InvalidArgumentException('Exec working directory must not be empty.');
        }

        if ($this->shell === '') {
            throw new \InvalidArgumentException('Exec shell must not be empty.');
        }
    }

    public function name(): string
    {
        return $this->taskName;
    }

    public function overlapPolicy(): OverlapPolicy
    {
        return $this->overlapPolicy;
    }

    public function createHandler(?ContainerFactory $containerFactory): TaskHandler
    {
        return new ExecTaskHandler(
            command: $this->command,
            workingDirectory: $this->workingDirectory,
            environment: $this->environment,
            shell: $this->shell,
        );
    }
}
