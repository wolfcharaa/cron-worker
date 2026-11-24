<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

use Wolfcharaa\Cron\Task\OverlapPolicy;

/** Data-only declaration of a shell command task. */
final readonly class ExecTaskConfig
{
    /** @param array<string, string> $environment */
    public function __construct(
        public string $taskName,
        public string $command,
        public ?string $workingDirectory = null,
        public array $environment = [],
        public string $shell = '/bin/sh',
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
        public ?string $expectedUser = null,
        public ?string $outputFile = null,
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

        if ($this->expectedUser === '') {
            throw new \InvalidArgumentException('Exec expected user must not be empty.');
        }

        if ($this->outputFile === '') {
            throw new \InvalidArgumentException('Exec output file must not be empty.');
        }
    }

    /** Returns the stable scheduler task name. */
    public function name(): string
    {
        return $this->taskName;
    }

    /** Returns the task overlap policy. */
    public function overlapPolicy(): OverlapPolicy
    {
        return $this->overlapPolicy;
    }
}
