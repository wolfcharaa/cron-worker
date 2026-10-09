<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Registry;

use Wolfcharaa\Cron\Configuration\Profile;
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
        $this->guardSharedTaskCollision($task);
        $profile = $this->profiles[$task->profile->name] ??= new CronProfileConfig($task->profile);
        $profile->add($task);

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
        foreach ($map->profileConfigs() as $profile) {
            $this->addProfile($profile);
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

    private function guardSharedTaskCollision(CronTaskDefinition $task): void
    {
        if ($task->profile->name !== Profile::ANY) {
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
