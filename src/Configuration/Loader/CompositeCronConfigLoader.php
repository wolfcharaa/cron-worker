<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Loader;

use Wolfcharaa\Cron\Registry\CronProfileMap;

final readonly class CompositeCronConfigLoader implements CronConfigLoaderInterface
{
    /** @var list<CronConfigLoaderInterface> */
    private array $loaders;

    public function __construct(CronConfigLoaderInterface ...$loaders)
    {
        $this->loaders = array_values($loaders);
    }

    public function load(): CronProfileMap
    {
        $map = new CronProfileMap();

        foreach ($this->loaders as $loader) {
            $map->merge($loader->load());
        }

        return $map;
    }
}
