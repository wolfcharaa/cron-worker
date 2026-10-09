<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task;

use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Schedule\Schedule;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;

final readonly class ScheduledTask extends Task
{
    /**
     * @param (callable(): mixed)|TaskHandler $handler
     * @param list<Profile|string|\BackedEnum> $profiles
     */
    public function __construct(
        string $name,
        public Schedule $schedule,
        callable|TaskHandler $handler,
        OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
        ?OneShotTask $next = null,
        array $profiles = [Profile::ANY],
    ) {
        $this->profiles = Profile::normalizeList($profiles);
        parent::__construct($name, $handler, $overlapPolicy, $next);
    }

    /** @var list<Profile> */
    public array $profiles;

    public function runsIn(Profile $active): bool
    {
        foreach ($this->profiles as $profile) {
            if ($profile->includes($active)) {
                return true;
            }
        }

        return false;
    }
}
