<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Attribute;

use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Task\OverlapPolicy;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class CronTask implements CronTaskAttribute
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
        string $schedule,
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

        $this->expression = new CronExpression($schedule);
        $this->profiles = Profile::normalizeMany($profiles);
        $this->chain = $chain ?? new ChainConfig();
    }

    /** @param class-string $serviceId */
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
