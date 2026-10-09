<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Builder;

use Wolfcharaa\Cron\Registry\CronProfileMap;

interface CronConfigBuilderInterface
{
    public function build(): CronProfileMap;
}
