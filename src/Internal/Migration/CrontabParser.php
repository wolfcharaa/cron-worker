<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Migration;

use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Schedule\CronExpression;

/** @internal Fail-closed parser for the supported crontab migration subset. */
final readonly class CrontabParser
{
    /**
     * @return list<array{expression: string, command: string, shell: string, environment: array<string, string>, workingDirectory: ?string, user: ?string}>
     */
    public function parse(string $contents, bool $systemFormat): array
    {
        $environment = [];
        $shell = '/bin/sh';
        $jobs = [];
        $lines = preg_split('/\R/', $contents);

        if ($lines === false) {
            throw new \LogicException('Unable to split crontab input.');
        }

        foreach ($lines as $index => $rawLine) {
            $lineNumber = $index + 1;
            $line = trim($rawLine);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches) === 1) {
                if ($matches[1] === 'CRON_TZ') {
                    throw $this->invalid($lineNumber, 'CRON_TZ cannot be represented per task; configure SchedulerOptions::timezone.');
                }

                $environment[$matches[1]] = $this->unquote($matches[2]);

                if ($matches[1] === 'SHELL') {
                    $shell = $environment[$matches[1]];
                }

                continue;
            }

            [$expression, $command, $user] = $this->job($line, $lineNumber, $systemFormat);

            if (str_contains($command, '%')) {
                throw $this->invalid($lineNumber, 'percent/newline crontab semantics are not supported.');
            }

            try {
                $expression = (new CronExpression($expression))->toString();
            } catch (\Throwable $throwable) {
                throw $this->invalid($lineNumber, $throwable->getMessage());
            }

            $jobs[] = [
                'expression' => $expression,
                'command' => $command,
                'shell' => $shell,
                'environment' => $environment,
                'workingDirectory' => $environment['HOME'] ?? null,
                'user' => $user,
            ];
        }

        if ($jobs === []) {
            throw InvalidCronConfiguration::at('<crontab>', '$', 'input must contain at least one supported job.');
        }

        return $jobs;
    }

    /** @return array{string, string, ?string} */
    private function job(string $line, int $lineNumber, bool $systemFormat): array
    {
        if (str_starts_with($line, '@')) {
            $parts = preg_split('/\s+/', $line, $systemFormat ? 3 : 2);
            $expected = $systemFormat ? 3 : 2;

            if ($parts === false || \count($parts) !== $expected) {
                throw $this->invalid($lineNumber, 'invalid shortcut entry.');
            }

            $expression = match ($parts[0]) {
                '@yearly', '@annually' => '0 0 1 1 *',
                '@monthly' => '0 0 1 * *',
                '@weekly' => '0 0 * * 0',
                '@daily', '@midnight' => '0 0 * * *',
                '@hourly' => '0 * * * *',
                '@reboot' => throw $this->invalid($lineNumber, '@reboot has no cron-expression equivalent.'),
                default => throw $this->invalid($lineNumber, 'unsupported crontab shortcut ' . $parts[0] . '.'),
            };

            return [
                $expression,
                $this->part($parts, $systemFormat ? 2 : 1, $lineNumber),
                $systemFormat ? $this->part($parts, 1, $lineNumber) : null,
            ];
        }

        $parts = preg_split('/\s+/', $line, $systemFormat ? 7 : 6);
        $expected = $systemFormat ? 7 : 6;

        if ($parts === false || \count($parts) !== $expected) {
            throw $this->invalid($lineNumber, 'expected five schedule fields, optional system user, and a command.');
        }

        return [
            implode(' ', \array_slice($parts, 0, 5)),
            $this->part($parts, $systemFormat ? 6 : 5, $lineNumber),
            $systemFormat ? $this->part($parts, 5, $lineNumber) : null,
        ];
    }

    /** @param list<string> $parts */
    private function part(array $parts, int $offset, int $line): string
    {
        return $parts[$offset] ?? throw $this->invalid($line, 'entry is missing a required field.');
    }

    private function invalid(int $line, string $message): InvalidCronConfiguration
    {
        return InvalidCronConfiguration::at('<crontab>', '$.lines[' . $line . ']', $message);
    }

    private function unquote(string $value): string
    {
        $value = trim($value);

        if (\strlen($value) >= 2 && $value[0] === $value[-1] && ($value[0] === '"' || $value[0] === "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
