<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Registry;

use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Exception\DuplicateProfileTask;

final class CronProfileMap
{
    /** @var array<string, array<string, CronTaskDefinition>> */
    private array $tasks = [];

    /** @var list<CronTaskDefinition> */
    private array $definitions = [];

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
            $this->guardUnique($task->name, $profile);
        }

        foreach ($task->profiles as $profile) {
            $this->tasks[$profile->name][$task->name] = $task;
        }

        $this->definitions[] = $task;

        return $this;
    }

    /** @return list<CronTaskDefinition> */
    public function forProfile(Profile|string|\BackedEnum $profile): array
    {
        $profile = Profile::normalize($profile);
        $tasks = [];

        foreach ($this->definitions as $task) {
            foreach ($task->profiles as $assignedProfile) {
                if (!$assignedProfile->includes($profile)) {
                    continue;
                }

                $tasks[] = $task;

                continue 2;
            }
        }

        return $tasks;
    }

    /** @return list<string> */
    public function profiles(): array
    {
        return array_keys($this->tasks);
    }

    private function guardUnique(string $taskName, Profile $profile): void
    {
        if (isset($this->tasks[$profile->name][$taskName])) {
            throw new DuplicateProfileTask($taskName, $profile->name);
        }

        if ($profile->name !== Profile::ANY && isset($this->tasks[Profile::ANY][$taskName])) {
            throw new DuplicateProfileTask($taskName, $profile->name);
        }

        if ($profile->name !== Profile::ANY) {
            return;
        }

        foreach ($this->tasks as $name => $tasks) {
            if (isset($tasks[$taskName])) {
                throw new DuplicateProfileTask($taskName, $name);
            }
        }
    }
}
