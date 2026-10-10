<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests;

use Psr\Container\ContainerInterface;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Exception\MissingContainerFactory;
use Wolfcharaa\Cron\Scheduler;
use Wolfcharaa\Cron\Task\TaskExecutionContext;
use Wolfcharaa\Cron\Tests\Stub\ArrayContainer;

#[Test]
final class SchedulerContainerTest
{
    public function resolvesServiceFromFreshContainerDuringExecution(): void
    {
        $factoryCalls = 0;
        $exitCode = $this->expectedExitCode();
        $scheduler = new Scheduler(
            containerFactory: static function () use (&$factoryCalls): ContainerInterface {
                ++$factoryCalls;

                return new ArrayContainer([
                    'command' => new class {
                        public function run(int $exitCode): int
                        {
                            return $exitCode;
                        }
                    },
                ]);
            },
        );
        $task = $scheduler
            ->serviceTask('service-task', 'command', 'run', [$exitCode])
            ->build();

        Assert::same($factoryCalls, 0);

        if ($task->execute() !== $exitCode || $task->execute() !== $exitCode) {
            throw new \LogicException('Service task did not return the configured exit code.');
        }

        Assert::same($factoryCalls, 2);
    }

    public function passesFreshContainerAndExecutionContextToCallback(): void
    {
        $scheduler = new Scheduler(
            containerFactory: static fn(): ContainerInterface => new ArrayContainer(['value' => 23]),
        );
        $context = new TaskExecutionContext(
            taskName: 'callback-task',
            plannedAt: new \DateTimeImmutable('2026-10-09 10:00:00 UTC'),
            startedAt: new \DateTimeImmutable('2026-10-09 10:00:01 UTC'),
        );
        $task = $scheduler
            ->containerTask(
                'callback-task',
                static function (ContainerInterface $container, TaskExecutionContext $received): int {
                    Assert::same($received->taskName, 'callback-task');
                    $value = $container->get('value');

                    if (!\is_int($value)) {
                        throw new \UnexpectedValueException('Expected an integer value.');
                    }

                    return $value;
                },
            )
            ->build();

        Assert::same($task->execute($context), 23);
    }

    public function requiresFactoryForContainerAwareTask(): void
    {
        $scheduler = new Scheduler();

        Expect::exception(MissingContainerFactory::class);
        $scheduler->serviceTask('service-task', 'command');
    }

    private function expectedExitCode(): int
    {
        return 17;
    }
}
