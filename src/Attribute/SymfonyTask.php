<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Attribute;

use Symfony\Component\Console\Command\Command;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\CronTaskDefinition;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Configuration\SymfonyCommandTaskConfig;
use Wolfcharaa\Cron\Configuration\SymfonyInput;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Schedule\Frequency\EveryDays;
use Wolfcharaa\Cron\Schedule\Frequency\EveryHours;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMonths;
use Wolfcharaa\Cron\Task\OverlapPolicy;

/** Declares a Symfony Command service as a scheduled task. */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class SymfonyTask
{
    public CronExpression $expression;

    /** @var list<Profile> */
    public array $profiles;

    public ChainConfig $chain;

    public SymfonyInput $input;

    /**
     * @param Profile|string|\BackedEnum|list<Profile|string|\BackedEnum> $profiles
     */
    public function __construct(
        CronExpression|EveryMinutes|EveryHours|EveryDays|EveryMonths|string $schedule,
        Profile|string|\BackedEnum|array $profiles = [Profile::DEFAULT],
        public ?string $name = null,
        ?SymfonyInput $input = null,
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
        ?ChainConfig $chain = null,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Symfony task attribute name must not be empty.');
        }

        $this->expression = match (true) {
            \is_string($schedule) => new CronExpression($schedule),
            $schedule instanceof EveryMinutes,
            $schedule instanceof EveryHours,
            $schedule instanceof EveryDays,
            $schedule instanceof EveryMonths => $schedule->expression(),
            default => $schedule,
        };
        $this->profiles = Profile::normalizeMany($profiles);
        $this->input = $input ?? new SymfonyInput();
        $this->chain = $chain ?? new ChainConfig();
    }

    /**
     * Converts this attribute into an immutable command-task definition.
     *
     * @param class-string $targetClass
     */
    public function toDefinition(string $targetClass): CronTaskDefinition
    {
        if (!is_a($targetClass, Command::class, true)) {
            throw new \LogicException(\sprintf(
                'Symfony cron task "%s" must extend %s.',
                $targetClass,
                Command::class,
            ));
        }

        return new CronTaskDefinition(
            task: new SymfonyCommandTaskConfig(
                commandId: $targetClass,
                input: $this->input,
                taskName: $this->name,
                overlapPolicy: $this->overlapPolicy,
            ),
            expression: $this->expression,
            profiles: $this->profiles,
            chain: $this->chain,
        );
    }
}
