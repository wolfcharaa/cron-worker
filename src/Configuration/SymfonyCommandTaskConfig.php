<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

use Symfony\Component\Console\Command\Command;
use Wolfcharaa\Cron\Task\OverlapPolicy;

/** Data-only declaration of a Symfony Console command service task. */
final readonly class SymfonyCommandTaskConfig
{
    /** @var class-string<Command> */
    public string $commandId;

    public SymfonyInput $input;

    public function __construct(
        string $commandId,
        ?SymfonyInput $input = null,
        public ?string $taskName = null,
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ) {
        if (!is_a($commandId, Command::class, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'Symfony command "%s" must extend %s.',
                $commandId,
                Command::class,
            ));
        }

        if ((new \ReflectionClass($commandId))->isAbstract()) {
            throw new \InvalidArgumentException(\sprintf(
                'Symfony command "%s" must be a concrete class.',
                $commandId,
            ));
        }

        $this->commandId = $commandId;
        $this->input = $input ?? new SymfonyInput();

        if ($this->taskName === '') {
            throw new \InvalidArgumentException('Cron task name must not be empty.');
        }
    }

    /** Returns the stable scheduler task name. */
    public function name(): string
    {
        return $this->taskName ?? $this->commandId;
    }

    /** Returns the task overlap policy. */
    public function overlapPolicy(): OverlapPolicy
    {
        return $this->overlapPolicy;
    }
}
