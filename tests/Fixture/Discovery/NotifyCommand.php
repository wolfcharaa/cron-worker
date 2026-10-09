<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Discovery;

final class NotifyCommand
{
    public function __invoke(): int
    {
        return 0;
    }
}
