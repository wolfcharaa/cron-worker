<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Task;

use Cron\CronExpression as CronParser;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Task\OverlapPolicy;

/** @internal */
final readonly class ScheduledTask
{
    private CronParser $parser;

    /** @param list<Profile> $profiles */
    public function __construct(
        public Task $task,
        CronExpression $expression,
        public array $profiles,
    ) {
        $this->parser = new CronParser($expression->toString());
    }

    public function nextRunAfter(\DateTimeImmutable $after): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable($this->parser->getNextRunDate(
            currentTime: $after,
            allowCurrentDate: false,
            timeZone: $after->getTimezone()->getName(),
        ));
    }

    public function runsIn(Profile $active): bool
    {
        foreach ($this->profiles as $profile) {
            if ($profile->includes($active)) {
                return true;
            }
        }

        return false;
    }

    public function overlapPolicy(): OverlapPolicy
    {
        return $this->task->overlapPolicy;
    }
}
