<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

enum OverlapPolicy: string
{
    case Allow = 'allow';
    case Forbid = 'forbid';
}
