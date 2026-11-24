<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;

#[Test]
final class YamlConfigLoaderTest
{
    public function acceptsTheVersionedNeutralCatalog(): void
    {
        $map = (new YamlConfigLoader("version: 1\nprofiles: {}\n"))->load();

        Assert::same($map->definitions(), []);
        Assert::same($map->profiles(), []);
    }

    public function loadsStrictExecJobsAndChains(): void
    {
        $map = (new YamlConfigLoader(<<<'YAML'
            version: 1
            defaults:
              shell: /bin/bash
              environment:
                APP_ENV: prod
            profiles:
              prod:
                jobs:
                  - name: root
                    schedule: '*/5 * * * *'
                    command: 'php bin/root.php'
                    then:
                      - name: next
                        command: 'php bin/next.php'
            YAML))->load();
        $definition = $map->forProfile('prod')[0] ?? throw new \LogicException('Root definition is missing.');
        $task = $definition->task;

        if (!$task instanceof ExecTaskConfig) {
            throw new \LogicException('YAML root must be an exec task.');
        }

        Assert::same($definition->name, 'root');
        Assert::same($definition->expression->toString(), '*/5 * * * *');
        Assert::same($task->environment, ['APP_ENV' => 'prod']);
        $chainTask = $definition->chain->tasks()[0] ?? throw new \LogicException('Chain task is missing.');
        Assert::same($chainTask->name(), 'next');
    }

    public function requiresVersionOne(): void
    {
        Expect::exception(InvalidCronConfiguration::class);
        (new YamlConfigLoader("profiles: {}\n"))->load();
    }

    public function rejectsUnknownKeys(): void
    {
        Expect::exception(InvalidCronConfiguration::class);
        (new YamlConfigLoader("version: 1\nprofiles: {}\nunknown: true\n"))->load();
    }
}
