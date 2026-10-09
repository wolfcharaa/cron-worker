<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

use Wolfcharaa\Cron\Task\Handler\TaskHandler;

final readonly class OneShotTask extends Task
{
    /** @param (callable(): mixed)|TaskHandler $handler */
    public function __construct(
        string $name,
        callable|TaskHandler $handler,
        OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
        ?self $next = null,
    ) {
        parent::__construct($name, $handler, $overlapPolicy, $next);
    }
}
