<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Builder\AttributeCronConfigBuilder;
use Wolfcharaa\Cron\Configuration\Builder\CompositeCronConfigBuilder;
use Wolfcharaa\Cron\Discovery\ClassListProvider;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;
use Wolfcharaa\Cron\Tests\Fixture\Discovery\FisUploadCommand;
use Wolfcharaa\Cron\Tests\Fixture\Discovery\RecoverStaleCommand;

#[Test]
final class ConfigBuilderTest
{
    public function buildsAndCombinesProfileMaps(): void
    {
        $builder = new CompositeCronConfigBuilder(
            new AttributeCronConfigBuilder(new ClassListProvider([FisUploadCommand::class])),
            new AttributeCronConfigBuilder(new ClassListProvider([RecoverStaleCommand::class])),
        );

        $map = $builder->build();

        Assert::same(array_keys($map->profileConfigs()), [
            'default',
            'server-10.11.166.100',
            'server-10.11.159.86',
            'any',
        ]);
        Assert::same(
            array_map(static fn(CronTaskDefinition $task): string => $task->name, $map->forProfile('default')),
            ['recover-stale', 'fis-upload'],
        );
    }

    public function discoversAttributesFromComposerProjectWithoutPathMap(): void
    {
        $map = AttributeCronConfigBuilder::forProject(\dirname(__DIR__, 2), includeDev: true)->build();

        Assert::true(isset($map->profileConfigs()['default']));
        Assert::same(
            array_map(static fn(CronTaskDefinition $task): string => $task->name, $map->forProfile('default')),
            ['recover-stale', 'fis-upload'],
        );
    }
}
