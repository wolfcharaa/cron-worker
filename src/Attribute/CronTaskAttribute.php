<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Attribute;

use Wolfcharaa\Cron\Registry\CronTaskDefinition;

interface CronTaskAttribute
{
    /** @param class-string $targetClass */
    public function toDefinition(string $targetClass): CronTaskDefinition;
}
