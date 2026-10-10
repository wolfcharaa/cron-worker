<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule;

use Cron\CronExpression as CronExpressionParser;

final readonly class CronSchedule implements Schedule
{
    public CronExpression $expression;

    private CronExpressionParser $parser;

    public function __construct(CronExpression|string $expression = CronExpression::DEFAULT)
    {
        $this->expression = \is_string($expression) ? new CronExpression($expression) : $expression;
        $this->parser = new CronExpressionParser($this->expression->toString());
    }

    public function nextRunAfter(\DateTimeImmutable $after): \DateTimeImmutable
    {
        $nextRun = $this->parser->getNextRunDate(
            currentTime: $after,
            allowCurrentDate: false,
            timeZone: $after->getTimezone()->getName(),
        );

        return \DateTimeImmutable::createFromMutable($nextRun);
    }
}
