<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Internal\Task\CallableTaskHandler;
use Wolfcharaa\Cron\Internal\Task\ContainerServiceTaskHandler;
use Wolfcharaa\Cron\Internal\Task\ScheduledTask;
use Wolfcharaa\Cron\Internal\Task\Task;
use Wolfcharaa\Cron\Internal\Task\TaskHandler;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Schedule\Frequency\EveryDays;
use Wolfcharaa\Cron\Schedule\Frequency\EveryHours;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMonths;
use Wolfcharaa\Cron\TaskExecutionContext;

/** Fluent builder for one root scheduled task. */
final class ScheduledTaskBuilder
{
    private CronExpression $expression;

    private OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid;

    private ?ChainTaskBuilder $next = null;

    /** @var list<Profile> */
    private array $profiles;

    /** @param \Closure(): ContainerInterface|null $containerFactory */
    private function __construct(
        public readonly string $name,
        private readonly TaskHandler $handler,
        private readonly ?\Closure $containerFactory,
    ) {
        if (trim($this->name) === '') {
            throw InvalidCronConfiguration::at('<manual>', '$.tasks', 'task name must not be empty.');
        }

        $this->expression = new CronExpression();
        $this->profiles = [new Profile(Profile::ANY)];
    }

    /**
     * @internal package assembly hook; not a supported consumer call
     * @param (\Closure(): ContainerInterface)|null $containerFactory
     */
    public static function assemble(string $name, mixed $handler, ?\Closure $containerFactory): self
    {
        if (!$handler instanceof TaskHandler) {
            throw new \LogicException('Scheduled task assembly requires an internal task handler.');
        }

        return new self($name, $handler, $containerFactory);
    }

    /** Replaces the schedule with an exact cron expression. */
    public function cron(CronExpression|string $expression): self
    {
        $this->expression = \is_string($expression) ? new CronExpression($expression) : $expression;

        return $this;
    }

    /** Replaces the schedule with a supported typed frequency. */
    public function every(EveryMinutes|EveryHours|EveryDays|EveryMonths $frequency): self
    {
        $this->expression = $frequency->expression();

        return $this;
    }

    /** Schedules every minute. */
    public function everyMinute(): self
    {
        return $this->every(new EveryMinutes());
    }

    /** Schedules a minute-field step. */
    public function everyMinutes(int $interval): self
    {
        return $this->every(new EveryMinutes($interval));
    }

    /** Schedules at minute zero of every hour. */
    public function hourly(): self
    {
        return $this->every(new EveryHours());
    }

    /** Schedules an hour-field step at minute zero. */
    public function everyHours(int $interval): self
    {
        return $this->every(new EveryHours($interval));
    }

    /** Schedules every hour at the selected minute. */
    public function hourlyAt(int $minute): self
    {
        $this->hourly();
        $this->expression = $this->expression->withMinute($minute);

        return $this;
    }

    /** Schedules daily at midnight. */
    public function daily(): self
    {
        return $this->every(new EveryDays());
    }

    /** Schedules a day-of-month field step at midnight. */
    public function everyDays(int $interval): self
    {
        return $this->every(new EveryDays($interval));
    }

    /** Schedules daily at a wall-clock time. */
    public function dailyAt(int $hour, int $minute = 0): self
    {
        return $this->daily()->at($hour, $minute);
    }

    /** Schedules at midnight on the first day of every month. */
    public function monthly(): self
    {
        return $this->every(new EveryMonths());
    }

    /** Schedules a month-field step on day one at midnight. */
    public function everyMonths(int $interval): self
    {
        return $this->every(new EveryMonths($interval));
    }

    /** Replaces hour and minute fields. */
    public function at(int $hour, int $minute = 0): self
    {
        $this->expression = $this->expression->withHour($hour)->withMinute($minute);

        return $this;
    }

    /** Restricts the expression to Monday through Friday. */
    public function weekdays(): self
    {
        return $this->onDaysOfWeek('MON', 'TUE', 'WED', 'THU', 'FRI');
    }

    /** Restricts the expression to Saturday and Sunday. */
    public function weekends(): self
    {
        return $this->onDaysOfWeek('SAT', 'SUN');
    }

    /** Restricts the expression to explicit cron weekday values. */
    public function onDaysOfWeek(int|string ...$days): self
    {
        if ($days === []) {
            throw new \InvalidArgumentException('At least one day of week is required.');
        }

        $this->expression = $this->expression->withDayOfWeek(implode(',', $days));

        return $this;
    }

    /** Allows children with this task name to overlap. */
    public function allowOverlapping(): self
    {
        $this->overlapPolicy = OverlapPolicy::Allow;

        return $this;
    }

    /** Forbids children with this task name from overlapping. */
    public function withoutOverlapping(): self
    {
        $this->overlapPolicy = OverlapPolicy::Forbid;

        return $this;
    }

    /** Replaces profile selection with one profile. */
    public function profile(Profile|string|\BackedEnum $profile): self
    {
        return $this->profiles($profile);
    }

    /** Replaces profile selection with one or more named profiles. */
    public function profiles(Profile|string|\BackedEnum ...$profiles): self
    {
        $this->profiles = Profile::normalizeList(array_values($profiles));

        return $this;
    }

    /**
     * Adds a callable successor that runs only after exit zero.
     *
     * @param (callable(): (int|null))|(callable(TaskExecutionContext): (int|null)) $handler
     */
    public function then(string $name, callable $handler): ChainTaskBuilder
    {
        $this->next = ChainTaskBuilder::assemble(
            $name,
            new CallableTaskHandler($handler, '$.tasks.' . $name),
            $this->containerFactory,
        );

        return $this->next;
    }

    /**
     * Adds a PSR-11 service successor that runs only after exit zero.
     *
     * @param array<int|string, mixed> $arguments
     */
    public function thenService(
        string $name,
        string $serviceId,
        ?string $method = null,
        array $arguments = [],
    ): ChainTaskBuilder {
        if (!array_is_list($arguments)) {
            throw InvalidCronConfiguration::at('<manual>', '$.tasks.' . $name, 'service arguments must be a positional list.');
        }

        if (trim($serviceId) === '' || $method === '') {
            throw InvalidCronConfiguration::at('<manual>', '$.tasks.' . $name, 'service id and optional method must not be empty.');
        }

        $factory = $this->containerFactory ?? throw InvalidCronConfiguration::at(
            '<manual>',
            '$.tasks.' . $name,
            'a container factory is required for service tasks.',
        );
        $this->next = ChainTaskBuilder::assemble(
            $name,
            new ContainerServiceTaskHandler($factory, $serviceId, $method, $arguments),
            $factory,
        );

        return $this->next;
    }

    /** @internal */
    public function internalTask(): object
    {
        $next = $this->next?->internalTask();

        if ($next !== null && !$next instanceof Task) {
            throw new \LogicException('Chain task builder returned an invalid internal value.');
        }

        return new ScheduledTask(
            new Task($this->name, $this->handler, $this->overlapPolicy, $next),
            $this->expression,
            $this->profiles,
        );
    }
}
