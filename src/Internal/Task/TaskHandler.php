<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Task;

use Wolfcharaa\Cron\TaskExecutionContext;

/** @internal */
interface TaskHandler
{
    public function execute(TaskExecutionContext $context): mixed;
}
