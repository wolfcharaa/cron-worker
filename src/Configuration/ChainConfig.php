<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

/** Immutable ordered task configs executed after a successful root or step. */
final readonly class ChainConfig
{
    /** @var list<ServiceTaskConfig|SymfonyCommandTaskConfig|ExecTaskConfig> */
    private array $tasks;

    public function __construct(
        ServiceTaskConfig|SymfonyCommandTaskConfig|ExecTaskConfig ...$tasks,
    ) {
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

    /**
     * Returns task configs in execution order.
     *
     * @return list<ServiceTaskConfig|SymfonyCommandTaskConfig|ExecTaskConfig>
     */
    public function tasks(): array
    {
        return $this->tasks;
    }
}
