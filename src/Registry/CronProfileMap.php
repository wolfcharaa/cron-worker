<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Registry;

use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Exception\DuplicateProfileTask;

final class CronProfileMap
{
    /** @var array<string, CronProfileConfig> */
    private array $profiles = [];

    /** @param iterable<CronTaskDefinition> $tasks */
    public function __construct(iterable $tasks = [])
    {
        foreach ($tasks as $task) {
            $this->add($task);
        }
    }

    public function add(CronTaskDefinition $task): self
    {
        foreach ($task->profiles as $profile) {
            $this->guardTaskCollision($task, $profile);
        }

        foreach ($task->profiles as $profile) {
            $config = $this->profiles[$profile->name] ??= new CronProfileConfig($profile);
            $config->add($task);
        }

        return $this;
    }

    public function addProfile(CronProfileConfig $profile): self
    {
        foreach ($profile->tasks() as $task) {
            $this->add($task);
        }

        return $this;
    }

    public function merge(self $map): self
    {
        $seen = [];

        foreach ($map->profileConfigs() as $profile) {
            foreach ($profile->tasks() as $task) {
                $id = spl_object_id($task);

                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $this->add($task);
            }
        }

        return $this;
    }

    /** @return list<CronTaskDefinition> */
    public function forProfile(Profile|string|\BackedEnum $profile): array
    {
        $profile = Profile::normalize($profile);
        $sharedProfile = $this->profiles[Profile::ANY] ?? null;
        $shared = $sharedProfile?->tasks() ?? [];

        if ($profile->name === Profile::ANY) {
            return array_values($shared);
        }

        $specificProfile = $this->profiles[$profile->name] ?? null;
        $specific = $specificProfile?->tasks() ?? [];

        foreach ($shared as $name => $task) {
            if (isset($specific[$name])) {
                throw new \LogicException(\sprintf(
                    'Task "%s" is defined in both "%s" and "%s" profiles.',
                    $name,
                    Profile::ANY,
                    $profile->name,
                ));
            }
        }

        return array_values([...$shared, ...$specific]);
    }

    /** @return list<string> */
    public function profiles(): array
    {
        return array_keys($this->profiles);
    }

    /** @return array<string, CronProfileConfig> */
    public function profileConfigs(): array
    {
        return $this->profiles;
    }

    private function guardTaskCollision(CronTaskDefinition $task, Profile $targetProfile): void
    {
        if (($this->profiles[$targetProfile->name] ?? null)?->has($task->name) === true) {
            throw new DuplicateProfileTask($task->name, $targetProfile->name);
        }

        if ($targetProfile->name !== Profile::ANY) {
            $sharedProfile = $this->profiles[Profile::ANY] ?? null;

            if ($sharedProfile !== null && isset($sharedProfile->tasks()[$task->name])) {
                throw new \LogicException(\sprintf(
                    'Task "%s" is already defined in the shared profile.',
                    $task->name,
                ));
            }

            return;
        }

        foreach ($this->profiles as $name => $profile) {
            if ($name !== Profile::ANY && isset($profile->tasks()[$task->name])) {
                throw new \LogicException(\sprintf(
                    'Shared task "%s" is already defined in profile "%s".',
                    $task->name,
                    $name,
                ));
            }
        }
    }
}
