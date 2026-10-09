<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

final readonly class ChainConfig
{
    /** @var list<TaskConfigInterface> */
    private array $tasks;

    public function __construct(TaskConfigInterface ...$tasks)
    {
        $names = [];

        foreach ($tasks as $task) {
            if (isset($names[$task->name()])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Cron chain contains duplicate task name "%s".',
                    $task->name(),
                ));
            }

            $names[$task->name()] = true;
        }

        $this->tasks = array_values($tasks);
    }

    /** @return list<TaskConfigInterface> */
    public function tasks(): array
    {
        return $this->tasks;
    }
}
