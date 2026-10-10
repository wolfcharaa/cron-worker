<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Discovery;

use Wolfcharaa\Cron\Attribute\CronTaskAttribute;
use Wolfcharaa\Cron\Registry\CronProfileMap;

final readonly class AttributeCronTaskDiscovery
{
    public function discover(ClassProviderInterface $provider): CronProfileMap
    {
        $map = new CronProfileMap();
        $seen = [];

        foreach ($provider->classes() as $class) {
            if (isset($seen[$class]) || !class_exists($class)) {
                continue;
            }

            $seen[$class] = true;
            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(
                CronTaskAttribute::class,
                \ReflectionAttribute::IS_INSTANCEOF,
            );

            if ($attributes !== [] && !$reflection->isInstantiable()) {
                throw new \LogicException(\sprintf(
                    'Attributed cron service "%s" must be an instantiable class.',
                    $class,
                ));
            }

            foreach ($attributes as $attribute) {
                $map->add($attribute->newInstance()->toDefinition($class));
            }
        }

        return $map;
    }
}
