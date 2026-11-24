<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Discovery;

use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Attribute\SymfonyTask;
use Wolfcharaa\Cron\Configuration\CronProfileMap;
use Wolfcharaa\Cron\Discovery\ClassProviderInterface;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;

/** @internal */
final readonly class AttributeCronTaskDiscovery
{
    public function discover(ClassProviderInterface $provider): CronProfileMap
    {
        $definitions = [];
        $seen = [];

        foreach ($provider->classes() as $class) {
            if (trim($class) === '' || !class_exists($class)) {
                throw InvalidCronConfiguration::at(
                    '<attribute-discovery>',
                    '$.classes',
                    'class providers must yield existing class-strings.',
                );
            }

            if (isset($seen[$class])) {
                continue;
            }

            $seen[$class] = true;
            $reflection = new \ReflectionClass($class);
            $attributes = [
                ...$reflection->getAttributes(CronTask::class),
                ...$reflection->getAttributes(SymfonyTask::class),
            ];

            if ($attributes !== [] && !$reflection->isInstantiable()) {
                throw InvalidCronConfiguration::at(
                    '<attribute-discovery>',
                    '$.classes.' . $class,
                    'attributed cron services must be instantiable.',
                );
            }

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $definitions[] = $instance->toDefinition($class);
            }
        }

        return new CronProfileMap($definitions);
    }
}
