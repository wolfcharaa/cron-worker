<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task\Handler;

use Wolfcharaa\Cron\Task\TaskExecutionContext;

interface TaskHandler
{
    public function execute(TaskExecutionContext $context): mixed;
}
