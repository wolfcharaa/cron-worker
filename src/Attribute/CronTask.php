<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Attribute;

use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\CronTaskDefinition;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Schedule\Frequency\EveryDays;
use Wolfcharaa\Cron\Schedule\Frequency\EveryHours;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMonths;
use Wolfcharaa\Cron\Task\OverlapPolicy;

/** Declares an ordinary PSR-11 service as a scheduled task. */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class CronTask
{
    public CronExpression $expression;

    /** @var list<Profile> */
    public array $profiles;

    public ChainConfig $chain;

    /**
     * @param Profile|string|\BackedEnum|list<Profile|string|\BackedEnum> $profiles
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(
        CronExpression|EveryMinutes|EveryHours|EveryDays|EveryMonths|string $schedule,
        Profile|string|\BackedEnum|array $profiles = [Profile::DEFAULT],
        public ?string $name = null,
        public ?string $method = null,
        public array $arguments = [],
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
        ?ChainConfig $chain = null,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Cron task attribute name must not be empty.');
        }

        if ($this->method === '') {
            throw new \InvalidArgumentException('Cron task attribute method must not be empty.');
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
        $this->chain = $chain ?? new ChainConfig();
    }

    /**
     * Converts this attribute into an immutable service-task definition.
     *
     * @param class-string $serviceId
     */
    public function toDefinition(string $serviceId): CronTaskDefinition
    {
        return new CronTaskDefinition(
            task: new ServiceTaskConfig(
                serviceId: $serviceId,
                method: $this->method,
                arguments: $this->arguments,
                taskName: $this->name,
                overlapPolicy: $this->overlapPolicy,
            ),
            expression: $this->expression,
            profiles: $this->profiles,
            chain: $this->chain,
        );
    }
}
