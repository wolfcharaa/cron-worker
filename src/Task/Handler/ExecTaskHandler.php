<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task\Handler;

use Wolfcharaa\Cron\Task\TaskExecutionContext;

final readonly class ExecTaskHandler implements TaskHandler
{
    /** @param array<string, string> $environment */
    public function __construct(
        private string $command,
        private ?string $workingDirectory = null,
        private array $environment = [],
        private string $shell = '/bin/sh',
    ) {}

    public function execute(TaskExecutionContext $context): int
    {
        $environment = array_replace(getenv(), $this->environment);
        $process = proc_open(
            [$this->shell, '-c', $this->command],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', 'php://stdout', 'w'],
                2 => ['file', 'php://stderr', 'w'],
            ],
            $pipes,
            $this->workingDirectory,
            $environment,
        );

        if (!\is_resource($process)) {
            throw new \RuntimeException(\sprintf('Unable to execute cron command: %s', $this->command));
        }

        return proc_close($process);
    }
}
