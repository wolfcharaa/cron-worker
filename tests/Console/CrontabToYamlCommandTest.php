<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Console;

use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;
use Wolfcharaa\Cron\Dev\Console\CrontabToYamlCommand;

#[Test]
final class CrontabToYamlCommandTest
{
    public function convertsFileThroughDevelopmentCommand(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'crontab-source-');
        $destination = tempnam(sys_get_temp_dir(), 'crontab-yaml-');

        if ($source === false || $destination === false) {
            throw new \RuntimeException('Unable to create converter test files.');
        }

        file_put_contents($source, '* * * * * php bin/heartbeat.php');

        try {
            $tester = new CommandTester(new CrontabToYamlCommand());
            $status = $tester->execute([
                'source' => $source,
                '--output' => $destination,
                '--profile' => 'stage',
            ]);

            Assert::same($status, 0);
            Assert::same(
                YamlConfigLoader::fromFile($destination)->load()->forProfile('stage')[0]->name ?? null,
                'job-1',
            );
        } finally {
            unlink($source);
            unlink($destination);
        }
    }
}
