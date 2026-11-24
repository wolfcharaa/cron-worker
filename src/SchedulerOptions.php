<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron;

use Wolfcharaa\Cron\Configuration\Profile;

/** Operational settings for one scheduler worker. */
final readonly class SchedulerOptions
{
    public Profile $profile;

    public \DateTimeZone $timezone;

    /** Creates validated worker-wide profile, timezone, concurrency and shutdown settings. */
    public function __construct(
        Profile|string|\BackedEnum $profile = Profile::DEFAULT,
        \DateTimeZone|string $timezone = 'UTC',
        public int $maxConcurrency = 4,
        public float $shutdownGracePeriodSeconds = 10.0,
    ) {
        $this->profile = Profile::normalize($profile);
        $this->timezone = \is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;

        if ($this->maxConcurrency < 1) {
            throw new \InvalidArgumentException('maxConcurrency must be at least 1.');
        }

        if ($this->shutdownGracePeriodSeconds <= 0) {
            throw new \InvalidArgumentException('shutdownGracePeriodSeconds must be greater than 0.');
        }
    }
}
