<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Chain;

final class StaticChainAction
{
    public static function call(int $exitCode): int
    {
        return $exitCode;
    }

    public function instanceMethod(): int
    {
        return 0;
    }

    public static function invalidResult(): string
    {
        return 'invalid';
    }
}
