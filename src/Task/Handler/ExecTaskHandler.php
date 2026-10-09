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
        private ?string $expectedUser = null,
        private ?string $outputFile = null,
    ) {}

    public function execute(TaskExecutionContext $context): int
    {
        $this->validateUser();
        $environment = array_replace(getenv(), $this->environment);
        $stdout = $this->outputFile === null
            ? ['file', 'php://stdout', 'w']
            : ['file', $this->outputFile, 'a'];
        $stderr = $this->outputFile === null
            ? ['file', 'php://stderr', 'w']
            : ['file', $this->outputFile, 'a'];
        $process = proc_open(
            [$this->shell, '-c', $this->command],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => $stdout,
                2 => $stderr,
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

    private function validateUser(): void
    {
        if ($this->expectedUser === null) {
            return;
        }

        $effectiveUserId = posix_geteuid();
        $account = posix_getpwuid($effectiveUserId);
        $actualUser = $account === false ? null : $account['name'];
        $matchesUserName = $actualUser === $this->expectedUser;
        $matchesUserId = ctype_digit($this->expectedUser)
            && (int) $this->expectedUser === $effectiveUserId;

        if (!$matchesUserName && !$matchesUserId) {
            throw new \RuntimeException(\sprintf(
                'Cron command expects Unix user "%s", running as "%s".',
                $this->expectedUser,
                $actualUser ?? (string) $effectiveUserId,
            ));
        }
    }
}
