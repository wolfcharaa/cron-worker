<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

use Symfony\Component\Console\Command\Command;
use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Exception\MissingContainerFactory;
use Wolfcharaa\Cron\Task\Handler\SymfonyCommandTaskHandler;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;
use Wolfcharaa\Cron\Task\OverlapPolicy;

final readonly class SymfonyCommandTaskConfig implements TaskConfigInterface
{
    /** @var class-string<Command> */
    public string $commandId;

    /**
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(
        string $commandId,
        public array $arguments = [],
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

        if ($this->taskName === '') {
            throw new \InvalidArgumentException('Cron task name must not be empty.');
        }
    }

    public function name(): string
    {
        return $this->taskName ?? $this->commandId;
    }

    public function overlapPolicy(): OverlapPolicy
    {
        return $this->overlapPolicy;
    }

    public function createHandler(?ContainerFactory $containerFactory): TaskHandler
    {
        if ($containerFactory === null) {
            throw new MissingContainerFactory();
        }

        return new SymfonyCommandTaskHandler(
            containerFactory: $containerFactory,
            commandId: $this->commandId,
            arguments: $this->arguments,
        );
    }
}
