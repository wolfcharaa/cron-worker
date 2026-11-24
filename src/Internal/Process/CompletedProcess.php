<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Process;

/** @internal */
final readonly class CompletedProcess
{
    public function __construct(
        public ChildProcess $process,
        public ?int $exitCode,
        public ?int $termSignal,
    ) {}

    public function succeeded(): bool
    {
        return $this->exitCode === 0 && $this->termSignal === null;
    }
}
