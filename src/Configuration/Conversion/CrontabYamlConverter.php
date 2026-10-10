<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Conversion;

use Symfony\Component\Yaml\Yaml;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\Loader\CrontabConfigLoader;
use Wolfcharaa\Cron\Configuration\Profile;

final readonly class CrontabYamlConverter
{
    public function convert(
        string $contents,
        Profile|string|\BackedEnum $profile = Profile::DEFAULT,
        bool $systemFormat = false,
        ?string $schema = null,
    ): string {
        $profile = Profile::normalize($profile);
        $definitions = (new CrontabConfigLoader(
            contents: $contents,
            profile: $profile,
            systemFormat: $systemFormat,
        ))->load()->forProfile($profile);
        $jobs = [];

        if ($definitions === []) {
            throw new \InvalidArgumentException('Crontab input must contain at least one job.');
        }

        foreach ($definitions as $index => $definition) {
            if (!$definition->task instanceof ExecTaskConfig) {
                throw new \LogicException('A crontab entry must produce an exec task.');
            }

            $task = $definition->task;
            $job = [
                'name' => \sprintf('job-%d', $index + 1),
                'schedule' => $definition->expression->toString(),
                'command' => $task->command,
                'shell' => $task->shell,
                'overlap_policy' => $task->overlapPolicy->value,
            ];

            if ($task->workingDirectory !== null) {
                $job['working_directory'] = $task->workingDirectory;
            }

            if ($task->environment !== []) {
                $job['environment'] = $task->environment;
            }

            if ($task->expectedUser !== null) {
                $job['user'] = $task->expectedUser;
            }

            if ($task->outputFile !== null) {
                $job['log'] = $task->outputFile;
            }

            $jobs[] = $job;
        }

        $document = [
            'version' => 1,
            'profiles' => [
                $profile->name => [
                    'jobs' => $jobs,
                ],
            ],
        ];

        if ($schema !== null && $schema !== '') {
            $document = ['$schema' => $schema, ...$document];
        }

        return Yaml::dump($document, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
