<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Runtime;

final readonly class SchedulerOptions
{
    public function __construct(
        public int $maxConcurrency = 4,
        public float $clockResyncIntervalSeconds = 30.0,
        public float $shutdownGracePeriodSeconds = 10.0,
    ) {
        if ($this->maxConcurrency < 1) {
            throw new \InvalidArgumentException('maxConcurrency must be at least 1.');
        }

        if ($this->clockResyncIntervalSeconds <= 0) {
            throw new \InvalidArgumentException('clockResyncIntervalSeconds must be greater than 0.');
        }

        if ($this->shutdownGracePeriodSeconds <= 0) {
            throw new \InvalidArgumentException('shutdownGracePeriodSeconds must be greater than 0.');
        }
    }
}
