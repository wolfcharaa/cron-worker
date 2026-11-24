<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\CronProfileMap;
use Wolfcharaa\Cron\Configuration\CronTaskDefinition;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\Loader\CompositeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\Loader\CronConfigLoaderInterface;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;

#[Test]
final class ConfigurationContractTest
{
    public function preservesDefinitionOrderAndResolvesSharedTasks(): void
    {
        $shared = $this->definition('shared', 'any');
        $prod = $this->definition('prod-only', 'prod');
        $map = new CronProfileMap([$shared, $prod]);

        Assert::same($map->definitions(), [$shared, $prod]);
        Assert::same($map->profiles(), ['any', 'prod']);
        Assert::same($map->forProfile('prod'), [$shared, $prod]);
        Assert::same($map->forProfile('dev'), [$shared]);
    }

    public function rejectsCollisionBetweenSharedAndNamedProfilesAtConstruction(): void
    {
        Expect::exception(InvalidCronConfiguration::class);
        new CronProfileMap([
            $this->definition('same', 'prod'),
            $this->definition('same', 'any'),
        ]);
    }

    public function rejectsDuplicateSharedTasksAtConstruction(): void
    {
        Expect::exception(InvalidCronConfiguration::class);
        new CronProfileMap([
            $this->definition('same', 'any'),
            $this->definition('same', 'any'),
        ]);
    }

    public function composesWithoutMutatingChildMaps(): void
    {
        $first = new CronProfileMap([$this->definition('first', 'prod')]);
        $second = new CronProfileMap([$this->definition('second', 'prod')]);
        $loader = new CompositeCronConfigLoader(
            new FixedLoader($first),
            new FixedLoader($second),
        );

        Assert::count($loader->load()->definitions(), 2);
        Assert::count($first->definitions(), 1);
        Assert::count($second->definitions(), 1);
    }

    private function definition(string $name, string $profile): CronTaskDefinition
    {
        return new CronTaskDefinition(
            new ExecTaskConfig($name, 'true'),
            '* * * * *',
            $profile,
        );
    }
}

final readonly class FixedLoader implements CronConfigLoaderInterface
{
    public function __construct(private CronProfileMap $map) {}

    public function load(): CronProfileMap
    {
        return $this->map;
    }
}
