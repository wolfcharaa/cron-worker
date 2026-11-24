<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Task;

use Wolfcharaa\Cron\TaskExecutionContext;

/** @internal */
final readonly class ExecTaskHandler implements TaskHandler
{
    /** @param array<string, string> $environment */
    public function __construct(
        private string $command,
        private ?string $workingDirectory,
        private array $environment,
        private string $shell,
        private ?string $expectedUser,
        private ?string $outputFile,
    ) {}

    public function execute(TaskExecutionContext $_context): int
    {
        $this->validateUser();
        $environment = array_replace(getenv(), $this->environment);
        $stdout = $this->outputFile === null ? ['file', 'php://stdout', 'w'] : ['file', $this->outputFile, 'a'];
        $stderr = $this->outputFile === null ? ['file', 'php://stderr', 'w'] : ['file', $this->outputFile, 'a'];
        $process = proc_open(
            [$this->shell, '-c', $this->command],
            [0 => ['file', '/dev/null', 'r'], 1 => $stdout, 2 => $stderr],
            $pipes,
            $this->workingDirectory,
            $environment,
        );

        if (!\is_resource($process)) {
            throw new \RuntimeException(\sprintf('Unable to execute cron command: %s', $this->command));
        }

        $exitCode = proc_close($process);

        if ($exitCode < 0 || $exitCode > 255) {
            throw new \RuntimeException(\sprintf('Cron command returned invalid exit status %d.', $exitCode));
        }

        return $exitCode;
    }

    private function validateUser(): void
    {
        if ($this->expectedUser === null) {
            return;
        }

        $effectiveUserId = posix_geteuid();
        $account = posix_getpwuid($effectiveUserId);
        $actualUser = $account === false ? null : $account['name'];

        if ($actualUser !== $this->expectedUser && !(ctype_digit($this->expectedUser) && (int) $this->expectedUser === $effectiveUserId)) {
            throw new \RuntimeException(\sprintf(
                'Cron command expects Unix user "%s", running as "%s".',
                $this->expectedUser,
                $actualUser ?? (string) $effectiveUserId,
            ));
        }
    }
}
