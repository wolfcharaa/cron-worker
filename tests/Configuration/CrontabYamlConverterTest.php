<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Symfony\Component\Yaml\Yaml;
use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Conversion\CrontabYamlConverter;
use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;

#[Test]
final class CrontabYamlConverterTest
{
    public function convertsClassicCrontabToLoadableYaml(): void
    {
        $yaml = (new CrontabYamlConverter())->convert(
            contents: <<<'CRON'
                SHELL=/bin/bash
                HOME=/var/www/app
                */15 * * * * php bin/console app:cleanup
                @daily php bin/console app:archive
                CRON,
            profile: 'prod',
            schema: '../vendor/romanfedorskij/cron/resources/schema/crontab.schema.json',
        );
        $document = Yaml::parse($yaml);

        Assert::true(\is_array($document));
        Assert::same($document['$schema'] ?? null, '../vendor/romanfedorskij/cron/resources/schema/crontab.schema.json');

        $tasks = (new YamlConfigLoader($yaml))->load()->forProfile('prod');
        $first = $tasks[0] ?? throw new \LogicException('Converted task is missing.');
        $second = $tasks[1] ?? throw new \LogicException('Second converted task is missing.');

        Assert::same($first->name, 'job-1');
        Assert::same($first->expression->toString(), '*/15 * * * *');
        Assert::same($second->expression->toString(), '0 0 * * *');
    }
}
