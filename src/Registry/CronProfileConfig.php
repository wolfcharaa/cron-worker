<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Registry;

use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Exception\DuplicateProfileTask;

final class CronProfileConfig
{
    /** @var array<string, CronTaskDefinition> */
    private array $tasks = [];

    public readonly Profile $profile;

    /** @param iterable<CronTaskDefinition> $tasks */
    public function __construct(Profile|string|\BackedEnum $profile, iterable $tasks = [])
    {
        $this->profile = Profile::normalize($profile);

        foreach ($tasks as $task) {
            $this->add($task);
        }
    }

    public function add(CronTaskDefinition $task): self
    {
        if (!$task->isDeclaredFor($this->profile)) {
            throw new \InvalidArgumentException(\sprintf(
                'Task "%s" is not declared for profile "%s".',
                $task->name,
                $this->profile->name,
            ));
        }

        if (isset($this->tasks[$task->name])) {
            throw new DuplicateProfileTask($task->name, $this->profile->name);
        }

        $this->tasks[$task->name] = $task;

        return $this;
    }

    /** @return array<string, CronTaskDefinition> */
    public function tasks(): array
    {
        return $this->tasks;
    }

    public function has(string $taskName): bool
    {
        return isset($this->tasks[$taskName]);
    }
}
