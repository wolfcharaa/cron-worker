<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule;

use Wolfcharaa\Cron\Schedule\Frequency\EveryTime;

final class ScheduleBuilder
{
    private CronExpression $expression;

    public function __construct(null|CronExpression|string $expression = null)
    {
        $this->cron($expression ?? CronExpression::DEFAULT);
    }

    public static function create(): self
    {
        return new self();
    }

    public static function fromCron(CronExpression|string $expression): self
    {
        return new self($expression);
    }

    public function cron(CronExpression|string $expression): self
    {
        $this->expression = \is_string($expression) ? new CronExpression($expression) : $expression;

        return $this;
    }

    public function every(EveryTime $time): self
    {
        $this->expression = $time->expression();

        return $this;
    }

    public function everyMinute(): self
    {
        return $this->every(EveryTime::minutes());
    }

    public function everyMinutes(int $interval): self
    {
        return $this->every(EveryTime::minutes($interval));
    }

    public function hourly(): self
    {
        return $this->every(EveryTime::hours());
    }

    public function everyHours(int $interval): self
    {
        return $this->every(EveryTime::hours($interval));
    }

    public function hourlyAt(int $minute): self
    {
        $this->hourly();
        $this->expression = $this->expression->withMinute($minute);

        return $this;
    }

    public function daily(): self
    {
        return $this->every(EveryTime::days());
    }

    public function everyDays(int $interval): self
    {
        return $this->every(EveryTime::days($interval));
    }

    public function dailyAt(int $hour, int $minute = 0): self
    {
        $this->daily();

        return $this->at($hour, $minute);
    }

    public function monthly(): self
    {
        return $this->every(EveryTime::months());
    }

    public function everyMonths(int $interval): self
    {
        return $this->every(EveryTime::months($interval));
    }

    public function at(int $hour, int $minute = 0): self
    {
        $this->expression = $this->expression
            ->withHour($hour)
            ->withMinute($minute);

        return $this;
    }

    public function weekdays(): self
    {
        return $this->onDaysOfWeek('MON', 'TUE', 'WED', 'THU', 'FRI');
    }

    public function weekends(): self
    {
        return $this->onDaysOfWeek('SAT', 'SUN');
    }

    public function onDaysOfWeek(int|string ...$days): self
    {
        if ($days === []) {
            throw new \InvalidArgumentException('At least one day of week is required.');
        }

        $this->expression = $this->expression->withDayOfWeek(implode(',', $days));

        return $this;
    }

    public function expression(): CronExpression
    {
        return $this->expression;
    }

    public function build(): CronSchedule
    {
        return new CronSchedule($this->expression);
    }
}
