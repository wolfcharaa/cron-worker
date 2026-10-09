<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule;

interface Schedule
{
    public function nextRunAfter(\DateTimeImmutable $after): \DateTimeImmutable;
}
