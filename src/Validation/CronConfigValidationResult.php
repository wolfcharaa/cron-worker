<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Validation;

use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;

final readonly class CronConfigValidationResult
{
    /** @param list<CronConfigValidationIssue> $issues */
    public function __construct(public array $issues) {}

    public function isValid(): bool
    {
        return $this->issues === [];
    }

    public function assertValid(): void
    {
        if ($this->isValid()) {
            return;
        }

        throw new InvalidCronConfiguration("Invalid cron configuration:\n- " . implode("\n- ", $this->issues));
    }
}
