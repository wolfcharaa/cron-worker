<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Migration;

use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Internal\Console\CrontabToYamlCommand;
use Wolfcharaa\Cron\Internal\Migration\CrontabYamlConverter;

#[Test]
final class CrontabMigrationTest
{
    public function convertsTheSupportedSubsetToLoadableYaml(): void
    {
        $yaml = (new CrontabYamlConverter())->convert(<<<'CRON'
            SHELL=/bin/bash
            HOME=/srv/app
            */15 * * * * php bin/cleanup.php
            @daily php bin/archive.php
            CRON, 'prod');
        $definitions = (new YamlConfigLoader($yaml))->load()->forProfile('prod');
        $first = $definitions[0] ?? throw new \LogicException('First converted definition is missing.');
        $second = $definitions[1] ?? throw new \LogicException('Second converted definition is missing.');

        Assert::same($first->expression->toString(), '*/15 * * * *');
        Assert::same($second->expression->toString(), '0 0 * * *');
    }

    public function rejectsLossyPercentSemantics(): void
    {
        Expect::exception(InvalidCronConfiguration::class);
        (new CrontabYamlConverter())->convert('* * * * * printf foo%bar');
    }

    public function rejectsRebootShortcut(): void
    {
        Expect::exception(InvalidCronConfiguration::class);
        (new CrontabYamlConverter())->convert('@reboot php warmup.php');
    }

    public function runsTheInstalledCommandAgainstFiles(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'cron-source-');
        $target = tempnam(sys_get_temp_dir(), 'cron-target-');

        if ($source === false || $target === false) {
            throw new \RuntimeException('Unable to create migration fixtures.');
        }

        try {
            file_put_contents($source, '* * * * * php heartbeat.php');
            $status = (new CommandTester(new CrontabToYamlCommand()))->execute([
                'source' => $source,
                '--output' => $target,
                '--profile' => 'stage',
            ]);
            Assert::same($status, 0);
            $definition = YamlConfigLoader::fromFile($target)->load()->forProfile('stage')[0]
                ?? throw new \LogicException('Converted definition is missing.');
            Assert::same($definition->name, 'job-1');
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }
}
