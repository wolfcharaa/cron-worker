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

    /** @var list<Profile> */
    public array $profiles;

    public \DateTimeZone $timezone;

    /**
     * @param list<Profile|string|\BackedEnum> $profiles
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(
        string $schedule,
        array $profiles = [Profile::ANY],
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
        $this->profiles = Profile::normalizeList($profiles);
        $this->timezone = new \DateTimeZone($timezone);
    }

    /** @param class-string $serviceId */
    public function toDefinition(string $serviceId): CronTaskDefinition
    {
        return new CronTaskDefinition(
            name: $this->name ?? $serviceId,
            serviceId: $serviceId,
            expression: $this->expression,
            profiles: $this->profiles,
            method: $this->method,
            arguments: $this->arguments,
            timezone: $this->timezone,
            overlapPolicy: $this->overlapPolicy,
        );
    }
}
