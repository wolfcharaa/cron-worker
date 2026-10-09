<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Task\OverlapPolicy;

#[Test]
final class YamlConfigLoaderTest
{
    public function loadsSeparateProfilesAndExecChain(): void
    {
        $map = (new YamlConfigLoader(<<<'YAML'
            version: 1
            profiles:
              stage:
                timezone: Europe/Moscow
                shell: /bin/bash
                working_directory: /var/www/app
                environment:
                  APP_ENV: stage
                jobs:
                  - name: import
                    schedule: "*/15 * * * *"
                    command: php bin/console app:import
                    overlap_policy: allow
                    environment:
                      BATCH_SIZE: "100"
                    then:
                      - name: notify
                        command: php bin/console app:notify
              prod:
                timezone: UTC
                jobs:
                  - name: import
                    schedule: "0 * * * *"
                    command: php bin/console app:import --prod
            YAML))->load();

        Assert::same($map->profiles(), ['stage', 'prod']);

        $stage = $map->forProfile('stage')[0] ?? throw new \LogicException('Stage task is missing.');
        $prod = $map->forProfile('prod')[0] ?? throw new \LogicException('Prod task is missing.');
        $next = $stage->chain->tasks()[0] ?? throw new \LogicException('Chain task is missing.');

        Assert::same($stage->expression->toString(), '*/15 * * * *');
        Assert::same($stage->timezone->getName(), 'Europe/Moscow');
        Assert::instanceOf($stage->task, ExecTaskConfig::class);
        Assert::same($stage->task->workingDirectory, '/var/www/app');
        Assert::same($stage->task->environment, ['APP_ENV' => 'stage', 'BATCH_SIZE' => '100']);
        Assert::same($stage->task->overlapPolicy, OverlapPolicy::Allow);
        Assert::same($next->name(), 'notify');
        Assert::same($prod->expression->toString(), '0 * * * *');
    }

    public function readsYamlAndYmlFiles(): void
    {
        foreach (['yaml', 'yml'] as $extension) {
            $path = sys_get_temp_dir() . '/cron-config-' . bin2hex(random_bytes(8)) . '.' . $extension;
            file_put_contents($path, <<<'YAML'
                version: 1
                profiles:
                  default:
                    jobs:
                      - name: heartbeat
                        schedule: "* * * * *"
                        command: php bin/heartbeat.php
                YAML);

            try {
                $tasks = YamlConfigLoader::fromFile($path)->load()->forProfile('default');
                Assert::same($tasks[0]->name ?? null, 'heartbeat');
            } finally {
                unlink($path);
            }
        }
    }

    public function loadsMvdcakeCatalogShapeWithoutLua(): void
    {
        $map = (new YamlConfigLoader(<<<'YAML'
            defaults:
              shell: /bin/bash
              path: /usr/local/bin:/usr/bin
              cwd: /var/www/html
              user: www-data
              log: /var/log/php/cron.log
            profiles:
              server-10.11.166.100:
                description: FTP-only plan
                jobs:
                  - name: fis-upload-prepared-packages
                    schedule: "0 * * * *"
                    command: php bin/console fis:upload-prepared-packages
            YAML))->load();

        $definition = $map->forProfile('server-10.11.166.100')[0]
            ?? throw new \LogicException('Mvdcake-compatible task is missing.');

        Assert::instanceOf($definition->task, ExecTaskConfig::class);
        Assert::same($definition->task->workingDirectory, '/var/www/html');
        Assert::same($definition->task->environment, ['PATH' => '/usr/local/bin:/usr/bin']);
        Assert::same($definition->task->expectedUser, 'www-data');
        Assert::same($definition->task->outputFile, '/var/log/php/cron.log');
    }

    public function rejectsUnknownConfigurationKeys(): void
    {
        Expect::exception(InvalidCronConfiguration::class);

        (new YamlConfigLoader(<<<'YAML'
            version: 1
            profiles:
              default:
                merged_settings: true
                jobs: []
            YAML))->load();
    }

    public function rejectsInvalidProfileMetadata(): void
    {
        Expect::exception(InvalidCronConfiguration::class);

        (new YamlConfigLoader(<<<'YAML'
            version: 1
            profiles:
              default:
                description: true
                jobs:
                  - name: heartbeat
                    schedule: "* * * * *"
                    command: php bin/heartbeat.php
            YAML))->load();
    }
}
