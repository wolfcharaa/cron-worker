<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Discovery;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Configuration\Loader\AttributeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Discovery\ClassListProvider;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;

#[Test]
final class AttributeConfigLoaderTest
{
    public function convertsBuiltInServiceAttributesToPublicDefinitions(): void
    {
        $map = (new AttributeCronConfigLoader(new ClassListProvider([AttributedService::class])))->load();
        $definition = $map->forProfile('prod')[0] ?? throw new \LogicException('Attribute definition is missing.');

        Assert::same($definition->name, 'heartbeat');
        Assert::same($definition->expression->toString(), '*/5 * * * *');
        Assert::instanceOf($definition->task, ServiceTaskConfig::class);
    }
}

#[CronTask(new EveryMinutes(5), profiles: 'prod', name: 'heartbeat')]
final class AttributedService
{
    public function __invoke(): int
    {
        return 0;
    }
}
