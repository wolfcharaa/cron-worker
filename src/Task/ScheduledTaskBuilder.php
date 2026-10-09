<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Exception\MissingContainerFactory;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Schedule\Frequency\EveryTime;
use Wolfcharaa\Cron\Schedule\ScheduleBuilder;
use Wolfcharaa\Cron\Task\Handler\ContainerCallbackHandler;
use Wolfcharaa\Cron\Task\Handler\ContainerServiceHandler;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;

final class ScheduledTaskBuilder
{
    /** @var (\Closure(): mixed)|TaskHandler */
    private readonly \Closure|TaskHandler $handler;

    private readonly ScheduleBuilder $schedule;

    private OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid;

    private ?OneShotTaskBuilder $next = null;

    /** @var list<Profile> */
    private array $profiles;

    /** @param (callable(): mixed)|TaskHandler $handler */
    public function __construct(
        public readonly string $name,
        callable|TaskHandler $handler,
        ?ScheduleBuilder $schedule = null,
        private readonly ?ContainerFactory $containerFactory = null,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Task name must not be empty.');
        }

        $this->handler = $handler instanceof TaskHandler ? $handler : \Closure::fromCallable($handler);
        $this->schedule = $schedule ?? ScheduleBuilder::create();
        $this->profiles = [new Profile(Profile::ANY)];
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

    public function profile(Profile|string|\BackedEnum $profile): self
    {
        return $this->profiles($profile);
    }

    public function profiles(Profile|string|\BackedEnum ...$profiles): self
    {
        $this->profiles = Profile::normalizeList(array_values($profiles));

        return $this;
    }

    /** @param (callable(): mixed)|TaskHandler $handler */
    public function then(string $name, callable|TaskHandler $handler): OneShotTaskBuilder
    {
        $this->next = new OneShotTaskBuilder($name, $handler, $this->containerFactory);

        return $this->next;
    }

    /** @param callable(ContainerInterface, TaskExecutionContext): mixed $handler */
    public function thenContainer(string $name, callable $handler): OneShotTaskBuilder
    {
        return $this->then($name, new ContainerCallbackHandler($this->requireContainerFactory(), $handler));
    }

    /** @param array<int|string, mixed> $arguments */
    public function thenService(
        string $name,
        string $serviceId,
        ?string $method = null,
        array $arguments = [],
    ): OneShotTaskBuilder {
        return $this->then($name, new ContainerServiceHandler(
            containerFactory: $this->requireContainerFactory(),
            serviceId: $serviceId,
            method: $method,
            arguments: $arguments,
        ));
    }

    public function build(): ScheduledTask
    {
        return new ScheduledTask(
            name: $this->name,
            schedule: $this->schedule->build(),
            handler: $this->handler,
            overlapPolicy: $this->overlapPolicy,
            next: $this->next?->build(),
            profiles: $this->profiles,
        );
    }

    private function requireContainerFactory(): ContainerFactory
    {
        return $this->containerFactory ?? throw new MissingContainerFactory();
    }
}
