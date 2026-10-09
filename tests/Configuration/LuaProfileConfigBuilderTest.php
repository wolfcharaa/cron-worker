<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Builder\LuaProfileConfigBuilder;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;

#[Test]
final class LuaProfileConfigBuilderTest
{
    public function buildsOneProfileFromMvdcakeRendererFormats(): void
    {
        $builder = new LuaProfileConfigBuilder(
            rendererCommand: ['lua5.3', 'render-cron-profile.lua'],
            catalog: 'profiles.yaml',
            profile: 'server-10.11.166.100',
            runner: static function (array $command): string {
                $format = array_pop($command);

                return match ($format) {
                    'summary' => <<<'SUMMARY'
                        cron.profile=server-10.11.166.100
                        cron.jobs=2
                        cron.job.1=0 * * * * www-data fis-upload-prepared-packages
                        cron.job.2=*/5 * * * * www-data fis-ftp-availability
                        SUMMARY,
                    'commands' => <<<'COMMANDS'
                        cd /var/www/html && mvdcake-cron-run php bin/console fis:upload-prepared-packages
                        cd /var/www/html && mvdcake-cron-run php bin/console fis:check-ftp-availability
                        COMMANDS,
                    default => throw new \LogicException('Unexpected renderer format.'),
                };
            },
        );

        $tasks = $builder->build()->forProfile('server-10.11.166.100');
        $first = $tasks[0] ?? throw new \LogicException('First Lua cron task is missing.');
        $second = $tasks[1] ?? throw new \LogicException('Second Lua cron task is missing.');

        Assert::same($first->name, 'fis-upload-prepared-packages');
        Assert::same($first->expression->toString(), '0 * * * *');
        Assert::instanceOf($first->task, ExecTaskConfig::class);
        Assert::same($second->name, 'fis-ftp-availability');
        Assert::same($second->expression->toString(), '*/5 * * * *');
    }

    public function rejectsDifferentJobAndCommandCounts(): void
    {
        $builder = new LuaProfileConfigBuilder(
            rendererCommand: 'renderer',
            catalog: 'profiles.yaml',
            runner: static fn(array $command): string => str_ends_with(implode(' ', $command), 'summary')
                ? 'cron.job.1=* * * * * www-data task'
                : '',
        );

        Expect::exception(\UnexpectedValueException::class);
        $builder->build();
    }
}
