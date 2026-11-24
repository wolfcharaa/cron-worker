<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Loader;

use Wolfcharaa\Cron\Configuration\CronProfileMap;
use Wolfcharaa\Cron\Discovery\ClassProviderInterface;
use Wolfcharaa\Cron\Internal\Discovery\AttributeCronTaskDiscovery;

/** Discovers built-in cron attributes from an explicit class source. */
final readonly class AttributeCronConfigLoader implements CronConfigLoaderInterface
{
    public function __construct(private ClassProviderInterface $classes) {}

    /** Loads built-in attributes into an immutable map. */
    public function load(): CronProfileMap
    {
        return (new AttributeCronTaskDiscovery())->discover($this->classes);
    }
}
