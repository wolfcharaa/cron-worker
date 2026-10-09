<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule;

use Cron\CronExpression as CronExpressionParser;

final readonly class CronSchedule implements Schedule
{
    public CronExpression $expression;

    private CronExpressionParser $parser;

    private \DateTimeZone $timezone;

    public function __construct(
        CronExpression|string $expression = CronExpression::DEFAULT,
        \DateTimeZone|string $timezone = 'UTC',
    ) {
        $this->expression = \is_string($expression) ? new CronExpression($expression) : $expression;
        $this->parser = new CronExpressionParser($this->expression->toString());
        $this->timezone = \is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;
    }

    public function nextRunAfter(\DateTimeImmutable $after): \DateTimeImmutable
    {
        $nextRun = $this->parser->getNextRunDate(
            currentTime: $after,
            allowCurrentDate: false,
            timeZone: $this->timezone->getName(),
        );

        return \DateTimeImmutable::createFromMutable($nextRun);
    }

    public function timezone(): \DateTimeZone
    {
        return $this->timezone;
    }
}
