<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Builder;

use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Registry\CronProfileMap;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;

final readonly class LuaProfileConfigBuilder implements CronConfigBuilderInterface
{
    /** @var list<string> */
    private array $rendererCommand;

    private Profile $profile;

    private \DateTimeZone $timezone;

    /** @var \Closure(list<string>): string */
    private \Closure $runner;

    /**
     * @param non-empty-string|array<int, string> $rendererCommand
     * @param null|callable(list<string>): string $runner
     */
    public function __construct(
        string|array $rendererCommand,
        private string $catalog,
        Profile|string|\BackedEnum $profile = Profile::DEFAULT,
        \DateTimeZone|string $timezone = 'UTC',
        private string $shell = '/bin/bash',
        ?callable $runner = null,
    ) {
        $this->rendererCommand = \is_string($rendererCommand) ? [$rendererCommand] : array_values($rendererCommand);
        $this->profile = Profile::normalize($profile);
        $this->timezone = \is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;
        $this->runner = $runner === null
            ? $this->runCommand(...)
            : \Closure::fromCallable($runner);

        if ($this->rendererCommand === [] || \in_array('', $this->rendererCommand, true)) {
            throw new \InvalidArgumentException('Lua renderer command must not be empty.');
        }

        if ($this->catalog === '') {
            throw new \InvalidArgumentException('Lua cron catalog path must not be empty.');
        }

        if ($this->shell === '') {
            throw new \InvalidArgumentException('Lua cron task shell must not be empty.');
        }
    }

    public function build(): CronProfileMap
    {
        $jobs = $this->parseSummary($this->render('summary'));
        $commands = $this->parseCommands($this->render('commands'));

        if (\count($jobs) !== \count($commands)) {
            throw new \UnexpectedValueException(\sprintf(
                'Lua cron renderer returned %d jobs and %d commands for profile "%s".',
                \count($jobs),
                \count($commands),
                $this->profile->name,
            ));
        }

        $map = new CronProfileMap();
        $position = 0;

        foreach ($jobs as $job) {
            $command = $commands[$position] ?? throw new \UnexpectedValueException('Lua cron command is missing.');
            ++$position;
            $map->add(new CronTaskDefinition(
                task: new ExecTaskConfig(
                    taskName: $job['name'],
                    command: $command,
                    shell: $this->shell,
                ),
                expression: $job['schedule'],
                profile: $this->profile,
                timezone: $this->timezone,
            ));
        }

        return $map;
    }

    private function render(string $format): string
    {
        return ($this->runner)([
            ...$this->rendererCommand,
            '--catalog',
            $this->catalog,
            '--profile',
            $this->profile->name,
            '--format',
            $format,
        ]);
    }

    /** @return list<array{name: string, schedule: string}> */
    private function parseSummary(string $summary): array
    {
        $jobs = [];
        $lines = preg_split('/\R/', $summary);

        if ($lines === false) {
            throw new \UnexpectedValueException('Unable to split Lua cron summary into lines.');
        }

        foreach ($lines as $line) {
            if (preg_match(
                '/^cron\.job\.(\d+)=(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+\S+\s+(.+)$/',
                trim($line),
                $matches,
            ) !== 1) {
                continue;
            }

            $jobs[(int) $matches[1]] = [
                'name' => $matches[7],
                'schedule' => implode(' ', \array_slice($matches, 2, 5)),
            ];
        }

        if ($jobs === []) {
            throw new \UnexpectedValueException(\sprintf(
                'Lua cron renderer returned no jobs for profile "%s".',
                $this->profile->name,
            ));
        }

        ksort($jobs);

        return array_values($jobs);
    }

    /** @return list<string> */
    private function parseCommands(string $commands): array
    {
        $lines = preg_split('/\R/', $commands);

        if ($lines === false) {
            throw new \UnexpectedValueException('Unable to split Lua cron commands into lines.');
        }

        return array_values(array_filter(
            array_map(trim(...), $lines),
            static fn(string $line): bool => $line !== '',
        ));
    }

    /** @param list<string> $command */
    private function runCommand(array $command): string
    {
        $process = proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!\is_resource($process)) {
            throw new \RuntimeException('Unable to start Lua cron profile renderer.');
        }

        $stdoutPipe = $pipes[1] ?? null;
        $stderrPipe = $pipes[2] ?? null;

        if (!\is_resource($stdoutPipe) || !\is_resource($stderrPipe)) {
            proc_terminate($process);
            proc_close($process);

            throw new \RuntimeException('Lua cron profile renderer did not expose output pipes.');
        }

        $stdout = stream_get_contents($stdoutPipe);
        $stderr = stream_get_contents($stderrPipe);
        fclose($stdoutPipe);
        fclose($stderrPipe);
        $exitCode = proc_close($process);

        if ($stdout === false || $stderr === false) {
            throw new \RuntimeException('Unable to read Lua cron profile renderer output.');
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException(\sprintf(
                'Lua cron profile renderer failed with exit code %d: %s',
                $exitCode,
                trim($stderr),
            ));
        }

        return $stdout;
    }
}
