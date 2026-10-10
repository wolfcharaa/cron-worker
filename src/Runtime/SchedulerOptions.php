<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Runtime;

use Wolfcharaa\Cron\Configuration\Profile;

final readonly class SchedulerOptions
{
    public Profile $profile;

    public \DateTimeZone $timezone;

    public function __construct(
        public int $maxConcurrency = 4,
        public float $clockResyncIntervalSeconds = 30.0,
        public float $shutdownGracePeriodSeconds = 10.0,
        Profile|string|\BackedEnum $profile = Profile::DEFAULT,
        \DateTimeZone|string $timezone = 'UTC',
    ) {
        $this->profile = Profile::normalize($profile);
        $this->timezone = \is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;

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
