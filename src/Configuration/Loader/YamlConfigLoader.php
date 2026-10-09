<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Loader;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Registry\CronProfileMap;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;
use Wolfcharaa\Cron\Task\OverlapPolicy;

final readonly class YamlConfigLoader implements CronConfigLoaderInterface
{
    public static function fromFile(string $path): self
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException(\sprintf('Unable to read YAML cron configuration: %s', $path));
        }

        return new self($contents, $path);
    }

    public function __construct(
        private string $contents,
        private string $source = '<string>',
    ) {}

    public function load(): CronProfileMap
    {
        try {
            $document = Yaml::parse($this->contents, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $exception) {
            throw InvalidCronConfiguration::at(
                $this->source,
                '$',
                $exception->getMessage(),
            );
        }

        $root = $this->mapping($document, '$');
        $this->onlyKeys($root, ['version', '$schema', 'defaults', 'profiles'], '$');
        $this->optionalString($root, '$schema', '$');

        if (\array_key_exists('version', $root) && $root['version'] !== 1) {
            throw $this->invalid('$.version', 'must be the integer 1.');
        }

        $defaults = $this->mapping($root['defaults'] ?? [], '$.defaults');
        $this->onlyKeys($defaults, [
            'timezone',
            'shell',
            'working_directory',
            'cwd',
            'path',
            'environment',
            'overlap_policy',
            'user',
            'log',
        ], '$.defaults');
        $profiles = $this->mapping($root['profiles'] ?? null, '$.profiles');

        if ($profiles === []) {
            throw $this->invalid('$.profiles', 'must define at least one profile.');
        }

        $map = new CronProfileMap();

        foreach ($profiles as $profileName => $profileValue) {
            if (!\is_string($profileName)) {
                throw $this->invalid('$.profiles', 'profile names must be strings.');
            }

            $this->loadProfile($map, new Profile($profileName), $profileValue, $defaults);
        }

        return $map;
    }

    /** @param array<array-key, mixed> $defaults */
    private function loadProfile(
        CronProfileMap $map,
        Profile $profile,
        mixed $value,
        array $defaults,
    ): void {
        $path = '$.profiles.' . $profile->name;
        $config = $this->mapping($value, $path);
        $this->onlyKeys($config, [
            'description',
            'timezone',
            'shell',
            'working_directory',
            'cwd',
            'path',
            'environment',
            'overlap_policy',
            'user',
            'log',
            'jobs',
        ], $path);
        $this->optionalText($config, 'description', $path);

        $timezone = $this->optionalString($config, 'timezone', $path)
            ?? $this->optionalString($defaults, 'timezone', '$.defaults')
            ?? 'UTC';
        $shell = $this->optionalString($config, 'shell', $path)
            ?? $this->optionalString($defaults, 'shell', '$.defaults')
            ?? '/bin/sh';
        $workingDirectory = $this->workingDirectory($config, $path)
            ?? $this->workingDirectory($defaults, '$.defaults');
        $environment = array_replace(
            $this->executionEnvironment($defaults, '$.defaults'),
            $this->executionEnvironment($config, $path),
        );
        $overlapPolicy = $this->overlapPolicy(
            $config,
            $path,
            $this->overlapPolicy($defaults, '$.defaults', OverlapPolicy::Forbid),
        );
        $expectedUser = $this->optionalString($config, 'user', $path)
            ?? $this->optionalString($defaults, 'user', '$.defaults');
        $outputFile = $this->optionalString($config, 'log', $path)
            ?? $this->optionalString($defaults, 'log', '$.defaults');
        $jobs = $this->list($config['jobs'] ?? null, $path . '.jobs');

        if ($jobs === []) {
            throw $this->invalid($path . '.jobs', 'must contain at least one job.');
        }

        foreach ($jobs as $index => $job) {
            $jobPath = \sprintf('%s.jobs[%d]', $path, $index);
            $job = $this->mapping($job, $jobPath);
            $this->onlyKeys($job, [
                'name',
                'schedule',
                'command',
                'timezone',
                'shell',
                'working_directory',
                'cwd',
                'path',
                'environment',
                'overlap_policy',
                'user',
                'log',
                'then',
            ], $jobPath);

            $task = $this->execTask(
                config: $job,
                path: $jobPath,
                inheritedShell: $shell,
                inheritedWorkingDirectory: $workingDirectory,
                inheritedEnvironment: $environment,
                inheritedOverlapPolicy: $overlapPolicy,
                inheritedExpectedUser: $expectedUser,
                inheritedOutputFile: $outputFile,
            );

            $map->add(new CronTaskDefinition(
                task: $task,
                expression: $this->requiredString($job, 'schedule', $jobPath),
                profile: $profile,
                timezone: $this->optionalString($job, 'timezone', $jobPath) ?? $timezone,
                chain: $this->chain(
                    $job['then'] ?? [],
                    $jobPath . '.then',
                    $task,
                ),
            ));
        }
    }

    /**
     * @param array<array-key, mixed> $config
     * @param array<string, string> $inheritedEnvironment
     */
    private function execTask(
        array $config,
        string $path,
        string $inheritedShell,
        ?string $inheritedWorkingDirectory,
        array $inheritedEnvironment,
        OverlapPolicy $inheritedOverlapPolicy,
        ?string $inheritedExpectedUser,
        ?string $inheritedOutputFile,
    ): ExecTaskConfig {
        return new ExecTaskConfig(
            taskName: $this->requiredString($config, 'name', $path),
            command: $this->requiredString($config, 'command', $path),
            workingDirectory: $this->workingDirectory($config, $path) ?? $inheritedWorkingDirectory,
            environment: array_replace(
                $inheritedEnvironment,
                $this->executionEnvironment($config, $path),
            ),
            shell: $this->optionalString($config, 'shell', $path) ?? $inheritedShell,
            overlapPolicy: $this->overlapPolicy($config, $path, $inheritedOverlapPolicy),
            expectedUser: $this->optionalString($config, 'user', $path) ?? $inheritedExpectedUser,
            outputFile: $this->optionalString($config, 'log', $path) ?? $inheritedOutputFile,
        );
    }

    private function chain(
        mixed $value,
        string $path,
        ExecTaskConfig $defaults,
    ): ChainConfig {
        $tasks = [];

        foreach ($this->list($value, $path) as $index => $task) {
            $taskPath = \sprintf('%s[%d]', $path, $index);
            $task = $this->mapping($task, $taskPath);
            $this->onlyKeys($task, [
                'name',
                'command',
                'shell',
                'working_directory',
                'cwd',
                'path',
                'environment',
                'overlap_policy',
                'user',
                'log',
            ], $taskPath);
            $tasks[] = $this->execTask(
                config: $task,
                path: $taskPath,
                inheritedShell: $defaults->shell,
                inheritedWorkingDirectory: $defaults->workingDirectory,
                inheritedEnvironment: $defaults->environment,
                inheritedOverlapPolicy: $defaults->overlapPolicy,
                inheritedExpectedUser: $defaults->expectedUser,
                inheritedOutputFile: $defaults->outputFile,
            );
        }

        return new ChainConfig(...$tasks);
    }

    /** @param array<array-key, mixed> $config */
    private function workingDirectory(array $config, string $path): ?string
    {
        $workingDirectory = $this->optionalString($config, 'working_directory', $path);
        $cwd = $this->optionalString($config, 'cwd', $path);

        if ($workingDirectory !== null && $cwd !== null) {
            throw $this->invalid($path, 'cannot contain both "working_directory" and "cwd".');
        }

        return $workingDirectory ?? $cwd;
    }

    /**
     * @param array<array-key, mixed> $config
     * @return array<string, string>
     */
    private function executionEnvironment(array $config, string $path): array
    {
        $environment = $this->environment($config['environment'] ?? [], $path . '.environment');
        $executablePath = $this->optionalString($config, 'path', $path);

        if ($executablePath !== null) {
            $environment['PATH'] = $executablePath;
        }

        return $environment;
    }

    /** @param array<array-key, mixed> $config */
    private function overlapPolicy(
        array $config,
        string $path,
        OverlapPolicy $default,
    ): OverlapPolicy {
        $value = $this->optionalString($config, 'overlap_policy', $path);

        if ($value === null) {
            return $default;
        }

        return OverlapPolicy::tryFrom($value)
            ?? throw $this->invalid($path . '.overlap_policy', 'must be "allow" or "forbid".');
    }

    /** @return array<string, string> */
    private function environment(mixed $value, string $path): array
    {
        $values = $this->mapping($value, $path);
        $environment = [];

        foreach ($values as $name => $environmentValue) {
            if (!\is_string($name) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1) {
                throw $this->invalid($path, 'environment variable names must be valid strings.');
            }

            if (!\is_string($environmentValue)) {
                throw $this->invalid($path . '.' . $name, 'must be a string.');
            }

            $environment[$name] = $environmentValue;
        }

        return $environment;
    }

    /** @param array<array-key, mixed> $values */
    private function requiredString(array $values, string $key, string $path): string
    {
        $value = $this->optionalString($values, $key, $path);

        if ($value === null) {
            throw $this->invalid($path . '.' . $key, 'is required.');
        }

        return $value;
    }

    /** @param array<array-key, mixed> $values */
    private function optionalString(array $values, string $key, string $path): ?string
    {
        if (!\array_key_exists($key, $values)) {
            return null;
        }

        $value = $values[$key];

        if (!\is_string($value) || trim($value) === '') {
            throw $this->invalid($path . '.' . $key, 'must be a non-empty string.');
        }

        return $value;
    }

    /** @param array<array-key, mixed> $values */
    private function optionalText(array $values, string $key, string $path): ?string
    {
        if (!\array_key_exists($key, $values)) {
            return null;
        }

        $value = $values[$key];

        if (!\is_string($value)) {
            throw $this->invalid($path . '.' . $key, 'must be a string.');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     * @param list<string> $allowed
     */
    private function onlyKeys(array $values, array $allowed, string $path): void
    {
        foreach (array_keys($values) as $key) {
            if (!\is_string($key) || !\in_array($key, $allowed, true)) {
                throw $this->invalid($path, \sprintf('contains unsupported key "%s".', (string) $key));
            }
        }
    }

    /** @return array<array-key, mixed> */
    private function mapping(mixed $value, string $path): array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw $this->invalid($path, 'must be a mapping.');
        }

        return $value;
    }

    /** @return list<mixed> */
    private function list(mixed $value, string $path): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw $this->invalid($path, 'must be a list.');
        }

        return $value;
    }

    private function invalid(string $path, string $message): InvalidCronConfiguration
    {
        return InvalidCronConfiguration::at($this->source, $path, $message);
    }
}
