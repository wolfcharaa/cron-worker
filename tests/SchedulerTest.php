<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests;

use Psr\Container\ContainerInterface;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\CronProfileMap;
use Wolfcharaa\Cron\Configuration\CronTaskDefinition;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Scheduler;
use Wolfcharaa\Cron\TaskExecutionContext;

#[Test]
final class SchedulerTest
{
    public function validatesManualCallableShapeBeforeRegistration(): void
    {
        Expect::exception(InvalidCronConfiguration::class);
        (new \ReflectionMethod(Scheduler::class, 'task'))->invoke(
            new Scheduler(),
            'invalid',
            static fn(string $value): int => \strlen($value),
        );
    }

    public function acceptsContextAwareManualCallable(): void
    {
        $builder = (new Scheduler())->task('valid', static fn(TaskExecutionContext $context): int => $context->taskName === 'valid' ? 0 : 1);

        Assert::same($builder->name, 'valid');
    }

    public function requiresPositionalServiceArguments(): void
    {
        Expect::exception(InvalidCronConfiguration::class);
        (new Scheduler(containerFactory: static fn() => new EmptyContainer()))
            ->serviceTask('service', 'service', arguments: ['named' => 'value']);
    }

    public function loadIsAtomicWhenASelectedDefinitionCannotCompile(): void
    {
        $scheduler = new Scheduler();
        $map = new CronProfileMap([
            new CronTaskDefinition(new ExecTaskConfig('first', 'true'), '* * * * *'),
            new CronTaskDefinition(new ServiceTaskConfig('service', taskName: 'second'), '* * * * *'),
        ]);

        try {
            $scheduler->load($map);
        } catch (InvalidCronConfiguration) {
            $builder = $scheduler->task('first', static fn(): int => 0);
            Assert::same($builder->name, 'first');

            return;
        }

        throw new \LogicException('Invalid configuration was unexpectedly accepted.');
    }
}

final class EmptyContainer implements ContainerInterface
{
    public function get(string $id): mixed
    {
        throw new \RuntimeException($id);
    }

    public function has(string $id): bool
    {
        return false;
    }
}
