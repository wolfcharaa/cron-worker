<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Schedule\Frequency\EveryTime;
use Wolfcharaa\Cron\Schedule\ScheduleBuilder;

final class ScheduledTaskBuilder
{
    /** @var \Closure(): mixed */
    private readonly \Closure $handler;

    private readonly ScheduleBuilder $schedule;

    private OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid;

    /** @param callable(): mixed $handler */
    public function __construct(
        public readonly string $name,
        callable $handler,
        ?ScheduleBuilder $schedule = null,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Task name must not be empty.');
        }

        $this->handler = \Closure::fromCallable($handler);
        $this->schedule = $schedule ?? ScheduleBuilder::create();
    }

    public function cron(CronExpression|string $expression): self
    {
        $this->schedule->cron($expression);

        return $this;
    }

    public function every(EveryTime $time): self
    {
        $this->schedule->every($time);

        return $this;
    }

    public function everyMinute(): self
    {
        $this->schedule->everyMinute();

        return $this;
    }

    public function everyMinutes(int $interval): self
    {
        $this->schedule->everyMinutes($interval);

        return $this;
    }

    public function hourly(): self
    {
        $this->schedule->hourly();

        return $this;
    }

    public function everyHours(int $interval): self
    {
        $this->schedule->everyHours($interval);

        return $this;
    }

    public function hourlyAt(int $minute): self
    {
        $this->schedule->hourlyAt($minute);

        return $this;
    }

    public function daily(): self
    {
        $this->schedule->daily();

        return $this;
    }

    public function everyDays(int $interval): self
    {
        $this->schedule->everyDays($interval);

        return $this;
    }

    public function dailyAt(int $hour, int $minute = 0): self
    {
        $this->schedule->dailyAt($hour, $minute);

        return $this;
    }

    public function monthly(): self
    {
        $this->schedule->monthly();

        return $this;
    }

    public function everyMonths(int $interval): self
    {
        $this->schedule->everyMonths($interval);

        return $this;
    }

    public function at(int $hour, int $minute = 0): self
    {
        $this->schedule->at($hour, $minute);

        return $this;
    }

    public function weekdays(): self
    {
        $this->schedule->weekdays();

        return $this;
    }

    public function weekends(): self
    {
        $this->schedule->weekends();

        return $this;
    }

    public function onDaysOfWeek(int|string ...$days): self
    {
        $this->schedule->onDaysOfWeek(...$days);

        return $this;
    }

    public function timezone(\DateTimeZone|string $timezone): self
    {
        $this->schedule->timezone($timezone);

        return $this;
    }

    public function allowOverlapping(): self
    {
        $this->overlapPolicy = OverlapPolicy::Allow;

        return $this;
    }

    public function withoutOverlapping(): self
    {
        $this->overlapPolicy = OverlapPolicy::Forbid;

        return $this;
    }

    public function build(): ScheduledTask
    {
        return new ScheduledTask(
            name: $this->name,
            schedule: $this->schedule->build(),
            handler: $this->handler,
            overlapPolicy: $this->overlapPolicy,
        );
    }
}
