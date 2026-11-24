<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Loader;

use Wolfcharaa\Cron\Configuration\CronProfileMap;

/** Produces one complete immutable cron configuration map. */
interface CronConfigLoaderInterface
{
    /** Loads and validates the source-level configuration. */
    public function load(): CronProfileMap;
}
