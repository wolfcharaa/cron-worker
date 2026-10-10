<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Registry;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Exception\DuplicateProfileTask;
use Wolfcharaa\Cron\Registry\CronProfileMap;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;

#[Test]
final class CronProfileMapTest
{
    public function buildsDeploymentPlansFromSharedAndNamedTasks(): void
    {
        $map = new CronProfileMap([
            $this->task('recover-stale', Profile::ANY),
            $this->task('prepare-packages', 'default'),
            $this->task('prepare-packages', 'server-10.11.159.86'),
            $this->task('upload-packages', 'default'),
            $this->task('upload-packages', 'server-10.11.166.100'),
        ]);

        Assert::same(
            array_map(
                static fn(CronTaskDefinition $task): string => $task->name,
                $map->forProfile('server-10.11.166.100'),
            ),
            ['recover-stale', 'upload-packages'],
        );
    }

    public function allowsSameTaskNameInDisjointProfiles(): void
    {
        $map = new CronProfileMap([
            $this->task('upload-packages', 'stage', '*/10 * * * *'),
            $this->task('upload-packages', 'prod', '0 * * * *'),
        ]);

        Assert::same($this->expressions($map->forProfile('stage')), ['*/10 * * * *']);
        Assert::same($this->expressions($map->forProfile('prod')), ['0 * * * *']);
    }

    public function registersOneDefinitionInSeveralProfiles(): void
    {
        $definition = $this->task('shared-config', ['stage', 'prod']);
        $map = new CronProfileMap([$definition]);

        Assert::same($map->forProfile('stage'), [$definition]);
        Assert::same($map->forProfile('prod'), [$definition]);
    }

    public function rejectsDuplicateTaskInsideOneProfile(): void
    {
        $map = new CronProfileMap([
            $this->task('duplicate', 'prod'),
        ]);

        Expect::exception(DuplicateProfileTask::class);
        $map->add($this->task('duplicate', 'prod'));
    }

    public function rejectsMultiProfileDefinitionAtomically(): void
    {
        $map = new CronProfileMap([
            $this->task('duplicate', 'prod'),
        ]);

        try {
            $map->add($this->task('duplicate', ['stage', 'prod']));

            throw new \LogicException('Expected duplicate profile task exception.');
        } catch (DuplicateProfileTask) {
            Assert::same($map->forProfile('stage'), []);
        }
    }

    public function rejectsNamedOverrideOfAnyTask(): void
    {
        $map = new CronProfileMap([
            $this->task('duplicate', Profile::ANY),
        ]);

        Expect::exception(\LogicException::class);
        $map->add($this->task('duplicate', 'prod'));
    }

    /** @param string|list<string> $profiles */
    private function task(
        string $name,
        string|array $profiles,
        string $expression = '* * * * *',
    ): CronTaskDefinition {
        return new CronTaskDefinition(
            task: new ServiceTaskConfig($name),
            expression: $expression,
            profiles: $profiles,
        );
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
