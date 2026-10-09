<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Loader;

use Wolfcharaa\Cron\Discovery\AttributeCronTaskDiscovery;
use Wolfcharaa\Cron\Discovery\ClassProviderInterface;
use Wolfcharaa\Cron\Registry\CronProfileMap;

final readonly class AttributeCronConfigLoader implements CronConfigLoaderInterface
{
    public function __construct(private ClassProviderInterface $classes) {}

    public function load(): CronProfileMap
    {
        return (new AttributeCronTaskDiscovery())->discover($this->classes);
    }
}
