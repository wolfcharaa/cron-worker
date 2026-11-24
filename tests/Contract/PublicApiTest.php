<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Contract;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Attribute\SymfonyTask;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\CronProfileMap;
use Wolfcharaa\Cron\Configuration\CronTaskDefinition;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\Loader\AttributeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\Loader\CompositeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\Loader\CronConfigLoaderInterface;
use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Configuration\SymfonyCommandTaskConfig;
use Wolfcharaa\Cron\Configuration\SymfonyInput;
use Wolfcharaa\Cron\Discovery\ClassListProvider;
use Wolfcharaa\Cron\Discovery\ClassProviderInterface;
use Wolfcharaa\Cron\Discovery\Psr4DirectoryClassProvider;
use Wolfcharaa\Cron\Exception\CronRuntimeException;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Schedule\Frequency\EveryDays;
use Wolfcharaa\Cron\Schedule\Frequency\EveryHours;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMonths;
use Wolfcharaa\Cron\Scheduler;
use Wolfcharaa\Cron\SchedulerOptions;
use Wolfcharaa\Cron\Task\ChainTaskBuilder;
use Wolfcharaa\Cron\Task\OverlapPolicy;
use Wolfcharaa\Cron\Task\ScheduledTaskBuilder;
use Wolfcharaa\Cron\TaskExecutionContext;

#[Test]
final class PublicApiTest
{
    /** @var list<class-string> */
    private const SYMBOLS = [
        Scheduler::class,
        SchedulerOptions::class,
        TaskExecutionContext::class,
        CronTask::class,
        SymfonyTask::class,
        Profile::class,
        ChainConfig::class,
        SymfonyInput::class,
        ServiceTaskConfig::class,
        SymfonyCommandTaskConfig::class,
        ExecTaskConfig::class,
        CronProfileMap::class,
        CronTaskDefinition::class,
        CronConfigLoaderInterface::class,
        AttributeCronConfigLoader::class,
        CompositeCronConfigLoader::class,
        YamlConfigLoader::class,
        ClassProviderInterface::class,
        ClassListProvider::class,
        Psr4DirectoryClassProvider::class,
        CronExpression::class,
        EveryMinutes::class,
        EveryHours::class,
        EveryDays::class,
        EveryMonths::class,
        OverlapPolicy::class,
        ScheduledTaskBuilder::class,
        ChainTaskBuilder::class,
        InvalidCronConfiguration::class,
        CronRuntimeException::class,
    ];

    public function exposesExactlyTheAcceptedNonInternalSourceFiles(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $actual = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace($root . '/', '', $file->getPathname());

            if (!str_starts_with($relative, 'Internal/')) {
                $actual[] = $relative;
            }
        }

        $expected = array_map(
            static fn(string $class): string => str_replace(
                '\\',
                '/',
                str_replace('Wolfcharaa\Cron\\', '', $class),
            ) . '.php',
            self::SYMBOLS,
        );
        sort($actual);
        sort($expected);
        Assert::same($actual, $expected);
    }

    public function keepsInternalTypesOutOfPublicSignatures(): void
    {
        foreach (self::SYMBOLS as $symbol) {
            $reflection = new \ReflectionClass($symbol);

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $this->assertPublicType($symbol . '::' . $method->getName(), $method->getReturnType());

                foreach ($method->getParameters() as $parameter) {
                    $this->assertPublicType($symbol . '::' . $method->getName() . '($' . $parameter->getName() . ')', $parameter->getType());
                }
            }

            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $this->assertPublicType($symbol . '::$' . $property->getName(), $property->getType());
            }
        }
    }

    private function assertPublicType(string $path, ?\ReflectionType $type): void
    {
        Assert::true($type === null || !str_contains((string) $type, 'Internal\\'), $path);
    }
}
