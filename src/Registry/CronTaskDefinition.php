<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Registry;

use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Configuration\TaskConfigInterface;
use Wolfcharaa\Cron\Schedule\CronExpression;

final readonly class CronTaskDefinition
{
    public string $name;

    public CronExpression $expression;

    /** @var list<Profile> */
    public array $profiles;

    /**
     * @param Profile|string|\BackedEnum|list<Profile|string|\BackedEnum> $profiles
     */
    public function __construct(
        public TaskConfigInterface $task,
        CronExpression|string $expression,
        Profile|string|\BackedEnum|array $profiles = [Profile::DEFAULT],
        ?ChainConfig $chain = null,
    ) {
        $this->name = $this->task->name();
        $this->expression = \is_string($expression) ? new CronExpression($expression) : $expression;
        $this->profiles = Profile::normalizeMany($profiles);
        $this->chain = $chain ?? new ChainConfig();
    }

    public ChainConfig $chain;

    public function isDeclaredFor(Profile $profile): bool
    {
        foreach ($this->profiles as $declaredProfile) {
            if ($declaredProfile->name === $profile->name) {
                return true;
            }
        }

        return false;
    }
}
