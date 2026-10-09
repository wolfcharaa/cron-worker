<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Exception;

final class InvalidCronConfiguration extends \InvalidArgumentException
{
    public static function at(string $source, string $path, string $message): self
    {
        return new self(\sprintf('Invalid cron configuration in %s at %s: %s', $source, $path, $message));
    }
}
