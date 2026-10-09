<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Registry;

use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Task\OverlapPolicy;

final readonly class CronTaskDefinition
{
    public CronExpression $expression;

    public \DateTimeZone $timezone;

    /** @var list<Profile> */
    public array $profiles;

    /**
     * @param list<Profile|string|\BackedEnum> $profiles
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(
        public string $name,
        public string $serviceId,
        CronExpression|string $expression,
        array $profiles = [Profile::ANY],
        public ?string $method = null,
        public array $arguments = [],
        \DateTimeZone|string $timezone = 'UTC',
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Cron task definition name must not be empty.');
        }

        if ($this->serviceId === '') {
            throw new \InvalidArgumentException('Cron task service id must not be empty.');
        }

        if ($this->method === '') {
            throw new \InvalidArgumentException('Cron task service method must not be empty.');
        }

        $this->expression = \is_string($expression) ? new CronExpression($expression) : $expression;
        $this->profiles = Profile::normalizeList($profiles);
        $this->timezone = \is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;
    }
}
