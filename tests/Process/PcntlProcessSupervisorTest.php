<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Process;

use Psr\Container\ContainerInterface;
use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Container\CallableContainerFactory;
use Wolfcharaa\Cron\Process\CompletedProcess;
use Wolfcharaa\Cron\Process\PcntlProcessSupervisor;
use Wolfcharaa\Cron\Schedule\CronSchedule;
use Wolfcharaa\Cron\Task\Handler\ContainerServiceHandler;
use Wolfcharaa\Cron\Task\ScheduledTask;
use Wolfcharaa\Cron\Tests\Stub\ArrayContainer;

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

    public function createsContainerInsideChildProcess(): void
    {
        $output = tempnam(sys_get_temp_dir(), 'cron-container-');

        if ($output === false) {
            throw new \RuntimeException('Unable to create a temporary output file.');
        }

        try {
            $factory = new CallableContainerFactory(
                static function () use ($output): ContainerInterface {
                    file_put_contents($output, (string) posix_getpid());

                    return new ArrayContainer([
                        'command' => static function (): void {},
                    ]);
                },
            );
            $supervisor = new PcntlProcessSupervisor();
            $now = new \DateTimeImmutable();
            $task = new ScheduledTask(
                'container-child',
                new CronSchedule('* * * * *'),
                new ContainerServiceHandler($factory, 'command'),
            );

            $supervisor->launch($task, $now, $now);
            $completed = $this->reapEventually($supervisor);
            $childPid = file_get_contents($output);

            if ($childPid === false) {
                throw new \RuntimeException('Unable to read the child process output.');
            }

            Assert::same($completed->exitCode, 0);
            Assert::true((int) $childPid !== posix_getpid());
        } finally {
            if (is_file($output)) {
                unlink($output);
            }
        }
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
