<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Process;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Process\CompletedProcess;
use Wolfcharaa\Cron\Process\PcntlProcessSupervisor;
use Wolfcharaa\Cron\Schedule\CronSchedule;
use Wolfcharaa\Cron\Task\ScheduledTask;

#[Test]
final class PcntlProcessSupervisorTest
{
    public function forksAndReapsTaskExitCode(): void
    {
        $supervisor = new PcntlProcessSupervisor();
        $now = new \DateTimeImmutable();
        $task = new ScheduledTask(
            'child-exit',
            new CronSchedule('* * * * *'),
            static fn(): int => 23,
        );

        $process = $supervisor->launch($task, $now, $now);

        Assert::true($process->pid > 0);
        Assert::same($supervisor->runningCount(), 1);

        $completed = $this->reapEventually($supervisor);

        Assert::same($completed->process->pid, $process->pid);
        Assert::same($completed->exitCode, 23);
        Assert::same($completed->termSignal, null);
        Assert::same($supervisor->runningCount(), 0);
    }

    private function reapEventually(PcntlProcessSupervisor $supervisor): CompletedProcess
    {
        $deadline = hrtime(true) + 2_000_000_000;

        do {
            $completed = $supervisor->reapExited();

            if ($completed !== []) {
                return $completed[0];
            }

            usleep(1_000);
        } while (hrtime(true) < $deadline);

        throw new \RuntimeException('Forked test process did not exit in time.');
    }
}
