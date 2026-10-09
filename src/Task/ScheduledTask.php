<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

use Wolfcharaa\Cron\Schedule\Schedule;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;

final readonly class ScheduledTask extends Task
{
    /** @param (callable(): mixed)|TaskHandler $handler */
    public function __construct(
        string $name,
        public Schedule $schedule,
        callable|TaskHandler $handler,
        OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
        ?OneShotTask $next = null,
    ) {
        parent::__construct($name, $handler, $overlapPolicy, $next);
    }
}
