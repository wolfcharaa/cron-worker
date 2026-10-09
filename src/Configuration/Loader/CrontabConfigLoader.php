<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Loader;

use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Registry\CronProfileMap;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;
use Wolfcharaa\Cron\Task\OverlapPolicy;

final readonly class CrontabConfigLoader implements CronConfigLoaderInterface
{
    private Profile $profile;

    private \DateTimeZone $timezone;

    public static function fromFile(
        string $path,
        Profile|string|\BackedEnum $profile = Profile::DEFAULT,
        bool $systemFormat = false,
        \DateTimeZone|string $timezone = 'UTC',
    ): self {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Unable to read crontab file: %s', $path));
        }

        return new self($contents, $profile, $systemFormat, $timezone);
    }

    public function __construct(
        private string $contents,
        Profile|string|\BackedEnum $profile = Profile::DEFAULT,
        private bool $systemFormat = false,
        \DateTimeZone|string $timezone = 'UTC',
    ) {
        $this->profile = Profile::normalize($profile);
        $this->timezone = \is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;
    }

    public function load(): CronProfileMap
    {
        $map = new CronProfileMap();
        $environment = [];
        $shell = '/bin/sh';
        $lines = preg_split('/\R/', $this->contents);

        if ($lines === false) {
            throw new \UnexpectedValueException('Unable to split crontab contents into lines.');
        }

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches) === 1) {
                $environment[$matches[1]] = $this->unquote($matches[2]);

                if ($matches[1] === 'SHELL') {
                    $shell = $environment[$matches[1]];
                }

                continue;
            }

            [$expression, $command, $user] = $this->parseJob($line, $lineNumber + 1);
            $name = $this->generatedName($expression, $command, $lineNumber + 1);
            $map->add(new CronTaskDefinition(
                task: new ExecTaskConfig(
                    taskName: $name,
                    command: $command,
                    workingDirectory: $environment['HOME'] ?? null,
                    environment: $environment,
                    shell: $shell,
                    overlapPolicy: OverlapPolicy::Allow,
                    expectedUser: $user,
                ),
                expression: $expression,
                profile: $this->profile,
                timezone: isset($environment['CRON_TZ'])
                    ? new \DateTimeZone($environment['CRON_TZ'])
                    : $this->timezone,
            ));
        }

        return $map;
    }

    /** @return array{string, string, ?string} */
    private function parseJob(string $line, int $lineNumber): array
    {
        if (str_starts_with($line, '@')) {
            $parts = preg_split('/\s+/', $line, $this->systemFormat ? 3 : 2);
            $expectedParts = $this->systemFormat ? 3 : 2;

            if ($parts === false || \count($parts) !== $expectedParts) {
                throw $this->invalidLine($lineNumber, $line);
            }

            return [
                $this->requiredPart($parts, 0, $lineNumber, $line),
                $this->requiredPart($parts, $this->systemFormat ? 2 : 1, $lineNumber, $line),
                $this->systemFormat ? $this->requiredPart($parts, 1, $lineNumber, $line) : null,
            ];
        }

        $parts = preg_split('/\s+/', $line, $this->systemFormat ? 7 : 6);
        $expectedParts = $this->systemFormat ? 7 : 6;

        if ($parts === false || \count($parts) !== $expectedParts) {
            throw $this->invalidLine($lineNumber, $line);
        }

        $expression = implode(' ', \array_slice($parts, 0, 5));
        $command = $this->requiredPart($parts, $this->systemFormat ? 6 : 5, $lineNumber, $line);

        return [
            $expression,
            $command,
            $this->systemFormat ? $this->requiredPart($parts, 5, $lineNumber, $line) : null,
        ];
    }

    private function generatedName(string $expression, string $command, int $lineNumber): string
    {
        return 'exec-' . substr(hash(
            'sha256',
            $this->profile->name . "\0" . $lineNumber . "\0" . $expression . "\0" . $command,
        ), 0, 12);
    }

    private function invalidLine(int $lineNumber, string $line): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf(
            'Invalid crontab entry at line %d: %s',
            $lineNumber,
            $line,
        ));
    }

    /** @param list<string> $parts */
    private function requiredPart(array $parts, int $offset, int $lineNumber, string $line): string
    {
        return $parts[$offset] ?? throw $this->invalidLine($lineNumber, $line);
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
