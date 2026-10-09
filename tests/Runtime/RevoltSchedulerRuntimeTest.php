<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Runtime;

use Psr\Log\NullLogger;
use Revolt\EventLoop;
use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Clock\SystemClock;
use Wolfcharaa\Cron\Process\PcntlProcessSupervisor;
use Wolfcharaa\Cron\Runtime\RevoltSchedulerRuntime;
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Schedule\Schedule;
use Wolfcharaa\Cron\Task\OneShotTask;
use Wolfcharaa\Cron\Task\ScheduledTask;

#[Test]
final class RevoltSchedulerRuntimeTest
{
    public function launchesOneShotChainAfterSuccessfulTasks(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'cron-chain-');

        if ($output === false) {
            throw new \RuntimeException('Unable to create a temporary output file.');
        }

        try {
            $third = new OneShotTask(
                'third',
                static function () use ($output): void {
                    file_put_contents($output, "third\n", FILE_APPEND | LOCK_EX);
                },
            );
            $next = new OneShotTask(
                'second',
                static function () use ($output): void {
                    file_put_contents($output, "second\n", FILE_APPEND | LOCK_EX);
                },
                next: $third,
            );
            $task = new ScheduledTask(
                name: 'first',
                schedule: $this->onceImmediately(),
                handler: static function () use ($output): void {
                    file_put_contents($output, "first\n", FILE_APPEND | LOCK_EX);
                },
                next: $next,
            );
            EventLoop::delay(0.15, static function (): void {
                posix_kill(posix_getpid(), SIGTERM);
            });
            $this->runtime()->run([$task]);

            Assert::same(file($output, FILE_IGNORE_NEW_LINES), ['first', 'second', 'third']);
        } finally {
            if (is_file($output)) {
                unlink($output);
            }
        }
    }

    public function doesNotLaunchSuccessorAfterFailedTask(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'cron-chain-');

        if ($output === false) {
            throw new \RuntimeException('Unable to create a temporary output file.');
        }

        try {
            $next = new OneShotTask(
                'second',
                static function () use ($output): void {
                    file_put_contents($output, "second\n", FILE_APPEND | LOCK_EX);
                },
            );
            $task = new ScheduledTask(
                name: 'first',
                schedule: $this->onceImmediately(),
                handler: static function () use ($output): int {
                    file_put_contents($output, "first\n", FILE_APPEND | LOCK_EX);

                    return 1;
                },
                next: $next,
            );

            EventLoop::delay(0.1, static function (): void {
                posix_kill(posix_getpid(), SIGTERM);
            });
            $this->runtime()->run([$task]);

            Assert::same(file($output, FILE_IGNORE_NEW_LINES), ['first']);
        } finally {
            if (is_file($output)) {
                unlink($output);
            }
        }
    }

    private function onceImmediately(): Schedule
    {
        return new class implements Schedule {
            private bool $first = true;

            public function nextRunAfter(\DateTimeImmutable $after): \DateTimeImmutable
            {
                if ($this->first) {
                    $this->first = false;

                    return $after;
                }

                return $after->modify('+1 day');
            }
        };
    }

    private function runtime(): RevoltSchedulerRuntime
    {
        return new RevoltSchedulerRuntime(
            supervisor: new PcntlProcessSupervisor(),
            clock: new SystemClock(),
            logger: new NullLogger(),
            options: new SchedulerOptions(
                maxConcurrency: 1,
                clockResyncIntervalSeconds: 0.05,
                shutdownGracePeriodSeconds: 0.1,
            ),
        );
    }
}
