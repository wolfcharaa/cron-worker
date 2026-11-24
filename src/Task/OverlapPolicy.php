<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

/** Whether another child with the same task name may run concurrently. */
enum OverlapPolicy: string
{
    case Allow = 'allow';
    case Forbid = 'forbid';
}
