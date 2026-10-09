<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Validation;

final readonly class CronConfigValidationIssue
{
    public function __construct(
        public string $profile,
        public string $task,
        public string $path,
        public string $message,
    ) {}

    public function __toString(): string
    {
        return \sprintf('%s: %s', $this->path, $this->message);
    }
}
