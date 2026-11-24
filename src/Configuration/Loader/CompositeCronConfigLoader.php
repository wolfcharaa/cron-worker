<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Loader;

use Wolfcharaa\Cron\Configuration\CronProfileMap;

/** Combines complete loader outputs without mutating child maps. */
final readonly class CompositeCronConfigLoader implements CronConfigLoaderInterface
{
    /** @var list<CronConfigLoaderInterface> */
    private array $loaders;

    public function __construct(CronConfigLoaderInterface ...$loaders)
    {
        $this->loaders = array_values($loaders);
    }

    /** Loads all sources in constructor order and validates collisions. */
    public function load(): CronProfileMap
    {
        $definitions = [];

        foreach ($this->loaders as $loader) {
            array_push($definitions, ...$loader->load()->definitions());
        }

        return new CronProfileMap($definitions);
    }
}
