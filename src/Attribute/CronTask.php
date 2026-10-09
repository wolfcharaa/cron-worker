<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Attribute;

use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Task\OverlapPolicy;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class CronTask
{
    public CronExpression $expression;

    public Profile $profile;

    public \DateTimeZone $timezone;

    /**
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(
        string $schedule,
        Profile|string|\BackedEnum $profile = Profile::DEFAULT,
        public ?string $name = null,
        public ?string $method = null,
        public array $arguments = [],
        string $timezone = 'UTC',
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Cron task attribute name must not be empty.');
        }

        if ($this->method === '') {
            throw new \InvalidArgumentException('Cron task attribute method must not be empty.');
        }

        $this->expression = new CronExpression($schedule);
        $this->profile = Profile::normalize($profile);
        $this->timezone = new \DateTimeZone($timezone);
    }

    /** @param class-string $serviceId */
    public function toDefinition(string $serviceId): CronTaskDefinition
    {
        return new CronTaskDefinition(
            name: $this->name ?? $serviceId,
            serviceId: $serviceId,
            expression: $this->expression,
            profile: $this->profile,
            method: $this->method,
            arguments: $this->arguments,
            timezone: $this->timezone,
            overlapPolicy: $this->overlapPolicy,
        );
    }
}
