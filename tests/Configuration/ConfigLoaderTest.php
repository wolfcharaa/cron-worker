<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Loader\AttributeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\Loader\CompositeCronConfigLoader;
use Wolfcharaa\Cron\Discovery\ClassListProvider;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;
use Wolfcharaa\Cron\Tests\Fixture\Discovery\FisUploadCommand;
use Wolfcharaa\Cron\Tests\Fixture\Discovery\RecoverStaleCommand;

#[Test]
final class ConfigLoaderTest
{
    public function loadsAndCombinesProfileMaps(): void
    {
        $loader = new CompositeCronConfigLoader(
            new AttributeCronConfigLoader(new ClassListProvider([FisUploadCommand::class])),
            new AttributeCronConfigLoader(new ClassListProvider([RecoverStaleCommand::class])),
        );

        $map = $loader->load();

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
}
