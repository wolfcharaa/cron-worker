<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Validation;

use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Configuration\TaskConfigInterface;
use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Registry\CronProfileMap;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;

final readonly class CronConfigValidator
{
    public function validate(
        CronProfileMap $map,
        ?ContainerFactory $containerFactory = null,
        null|Profile|string|\BackedEnum $profile = null,
    ): CronConfigValidationResult {
        $issues = [];

        foreach ($this->definitions($map, $profile) as $definition) {
            $root = \sprintf(
                'profiles.%s.tasks.%s',
                $definition->profile->name,
                $definition->name,
            );
            $this->validateTask(
                $definition->task,
                $definition->profile->name,
                $root,
                $containerFactory,
                $issues,
            );

            foreach ($definition->chain->tasks() as $position => $task) {
                $this->validateTask(
                    $task,
                    $definition->profile->name,
                    \sprintf('%s.then.%d.%s', $root, $position, $task->name()),
                    $containerFactory,
                    $issues,
                );
            }
        }

        return new CronConfigValidationResult($issues);
    }

    public function assertValid(
        CronProfileMap $map,
        ?ContainerFactory $containerFactory = null,
        null|Profile|string|\BackedEnum $profile = null,
    ): void {
        $this->validate($map, $containerFactory, $profile)->assertValid();
    }

    /** @return list<CronTaskDefinition> */
    private function definitions(
        CronProfileMap $map,
        null|Profile|string|\BackedEnum $profile,
    ): array {
        if ($profile !== null) {
            return $map->forProfile($profile);
        }

        $definitions = [];

        foreach ($map->profileConfigs() as $profileConfig) {
            array_push($definitions, ...array_values($profileConfig->tasks()));
        }

        return $definitions;
    }

    /** @param list<CronConfigValidationIssue> $issues */
    private function validateTask(
        TaskConfigInterface $task,
        string $profile,
        string $path,
        ?ContainerFactory $containerFactory,
        array &$issues,
    ): void {
        try {
            $task->createHandler($containerFactory);
        } catch (\Throwable $throwable) {
            $issues[] = new CronConfigValidationIssue(
                profile: $profile,
                task: $task->name(),
                path: $path,
                message: $throwable->getMessage(),
            );
        }
    }
}
