<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Loader;

use Wolfcharaa\Cron\Registry\CronProfileMap;

interface CronConfigLoaderInterface
{
    public function load(): CronProfileMap;
}
