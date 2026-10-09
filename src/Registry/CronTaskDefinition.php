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

    public \DateTimeZone $timezone;

    public Profile $profile;

    public function __construct(
        public TaskConfigInterface $task,
        CronExpression|string $expression,
        Profile|string|\BackedEnum $profile = Profile::DEFAULT,
        \DateTimeZone|string $timezone = 'UTC',
        ?ChainConfig $chain = null,
    ) {
        $this->name = $this->task->name();
        $this->expression = \is_string($expression) ? new CronExpression($expression) : $expression;
        $this->profile = Profile::normalize($profile);
        $this->timezone = \is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;
        $this->chain = $chain ?? new ChainConfig();
    }

    public ChainConfig $chain;
}
