<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Runtime;

use Psr\Clock\ClockInterface;
use Psr\Log\NullLogger;
use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Internal\Runtime\PcntlSchedulerRuntime;
use Wolfcharaa\Cron\Internal\Task\CallableTaskHandler;
use Wolfcharaa\Cron\Internal\Task\ScheduledTask;
use Wolfcharaa\Cron\Internal\Task\Task;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\SchedulerOptions;
use Wolfcharaa\Cron\Task\OverlapPolicy;

#[Test]
final class PcntlSchedulerRuntimeTest
{
    public function runsSuccessfulChainsAndStopsOnSignal(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'cron-chain-');

        if ($output === false) {
            throw new \RuntimeException('Unable to create runtime output.');
        }

        try {
            $next = new Task(
                'next',
                new CallableTaskHandler(static function () use ($output): int {
                    file_put_contents($output, "next\n", FILE_APPEND | LOCK_EX);
                    posix_kill(posix_getppid(), SIGTERM);

                    return 0;
                }, '$.next'),
                OverlapPolicy::Forbid,
            );
            $root = new Task(
                'root',
                new CallableTaskHandler(static function () use ($output): int {
                    file_put_contents($output, "root\n", FILE_APPEND | LOCK_EX);

                    return 0;
                }, '$.root'),
                OverlapPolicy::Forbid,
                $next,
            );

            $this->runtime()->run([$this->scheduled($root)]);
            Assert::same(file($output, FILE_IGNORE_NEW_LINES), ['root', 'next']);
        } finally {
            @unlink($output);
        }
    }

    public function escalatesToKillAfterTheMonotonicGracePeriod(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'cron-grace-');

        if ($output === false) {
            throw new \RuntimeException('Unable to create runtime output.');
        }

        try {
            $task = new Task(
                'stubborn',
                new CallableTaskHandler(static function () use ($output): int {
                    pcntl_signal(SIGTERM, SIG_IGN);
                    file_put_contents($output, 'started', LOCK_EX);
                    posix_kill(posix_getppid(), SIGTERM);
                    usleep(5_000_000);

                    return 0;
                }, '$.stubborn'),
                OverlapPolicy::Forbid,
            );
            $started = hrtime(true);
            $this->runtime(0.05)->run([$this->scheduled($task)]);
            $elapsed = (hrtime(true) - $started) / 1_000_000_000;

            Assert::same(file_get_contents($output), 'started');
            Assert::true($elapsed < 1.0);
        } finally {
            @unlink($output);
        }
    }

    private function scheduled(Task $task): ScheduledTask
    {
        return new ScheduledTask(
            $task,
            new CronExpression('* * * * *'),
            [new Profile(Profile::ANY)],
        );
    }

    private function runtime(float $grace = 0.5): PcntlSchedulerRuntime
    {
        return new PcntlSchedulerRuntime(
            new NullLogger(),
            new SchedulerOptions(maxConcurrency: 1, shutdownGracePeriodSeconds: $grace),
            clock: new AdvancingClock(),
        );
    }
}

final class AdvancingClock implements ClockInterface
{
    private int $calls = 0;

    public function now(): \DateTimeImmutable
    {
        return ++$this->calls === 1
            ? new \DateTimeImmutable('2026-01-01 00:00:59.900000 UTC')
            : new \DateTimeImmutable('2026-01-01 00:01:00.100000 UTC');
    }
}
