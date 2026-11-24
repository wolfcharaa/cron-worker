<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Clock;

use Psr\Clock\ClockInterface;

/** @internal */
final readonly class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
