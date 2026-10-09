<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Discovery;

use Psr\Container\ContainerInterface;
use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Discovery\AttributeCronTaskDiscovery;
use Wolfcharaa\Cron\Discovery\Psr4DirectoryClassProvider;
use Wolfcharaa\Cron\Registry\CronProfileMap;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Schedule\CronSchedule;
use Wolfcharaa\Cron\Scheduler;
use Wolfcharaa\Cron\Task\OverlapPolicy;
use Wolfcharaa\Cron\Tests\Fixture\Discovery\FisUploadCommand;
use Wolfcharaa\Cron\Tests\Fixture\Discovery\RecoverStaleCommand;
use Wolfcharaa\Cron\Tests\Stub\ArrayContainer;

#[Test]
final class AttributeCronTaskDiscoveryTest
{
    public function discoversRepeatableAttributesAndMultipleProfiles(): void
    {
        $map = $this->discover();

        Assert::same($this->names($map->forProfile('default')), ['recover-stale', 'fis-upload']);
        Assert::same($this->names($map->forProfile('server-10.11.166.100')), ['recover-stale', 'fis-upload']);
        Assert::same($this->expressions($map->forProfile('server-10.11.159.86')), [
            '* * * * *',
            '0 * * * *',
        ]);
    }

    public function loadsActiveProfileIntoScheduler(): void
    {
        $scheduler = new Scheduler(
            options: new SchedulerOptions(profile: 'server-10.11.166.100'),
            containerFactory: static fn(): ContainerInterface => new ArrayContainer([
                FisUploadCommand::class => new FisUploadCommand(),
                RecoverStaleCommand::class => new RecoverStaleCommand(),
            ]),
        );
        $scheduler->load($this->discover());
        $tasks = $scheduler->tasks();

        Assert::same(array_keys($tasks), ['recover-stale', 'fis-upload']);
        $upload = $tasks['fis-upload'] ?? throw new \LogicException('Discovered upload task is missing.');

        if (!$upload->schedule instanceof CronSchedule) {
            throw new \LogicException('Discovered upload task must use a cron schedule.');
        }

        Assert::same($upload->schedule->expression->toString(), '*/10 * * * *');
        Assert::same($upload->schedule->timezone()->getName(), 'Europe/Moscow');
        Assert::same($upload->overlapPolicy, OverlapPolicy::Allow);
        Assert::same($upload->execute(), 0);
    }

    private function discover(): CronProfileMap
    {
        return (new AttributeCronTaskDiscovery())->discover(new Psr4DirectoryClassProvider([
            'Wolfcharaa\Cron\Tests\Fixture\Discovery\\' => \dirname(__DIR__) . '/Fixture/Discovery',
        ]));
    }

    /**
     * @param list<CronTaskDefinition> $tasks
     * @return list<string>
     */
    private function names(array $tasks): array
    {
        return array_map(static fn(CronTaskDefinition $task): string => $task->name, $tasks);
    }

    /**
     * @param list<CronTaskDefinition> $tasks
     * @return list<string>
     */
    private function expressions(array $tasks): array
    {
        return array_map(
            static fn(CronTaskDefinition $task): string => $task->expression->toString(),
            $tasks,
        );
    }
}
