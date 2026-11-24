<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

use Wolfcharaa\Cron\Schedule\CronExpression;

/** One immutable scheduled task declaration. */
final readonly class CronTaskDefinition
{
    public string $name;

    public CronExpression $expression;

    /** @var list<Profile> */
    public array $profiles;

    public ChainConfig $chain;

    /**
     * @param Profile|string|\BackedEnum|list<Profile|string|\BackedEnum> $profiles
     */
    public function __construct(
        public ServiceTaskConfig|SymfonyCommandTaskConfig|ExecTaskConfig $task,
        CronExpression|string $expression,
        Profile|string|\BackedEnum|array $profiles = [Profile::DEFAULT],
        ?ChainConfig $chain = null,
    ) {
        $this->name = $this->task->name();
        $this->expression = \is_string($expression) ? new CronExpression($expression) : $expression;
        $this->profiles = Profile::normalizeMany($profiles);
        $this->chain = $chain ?? new ChainConfig();
    }
}
