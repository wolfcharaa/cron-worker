<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Exception;

/** Contextual source/profile/task failure detected before the runtime starts. */
final class InvalidCronConfiguration extends \InvalidArgumentException
{
    /** Creates a diagnostic with source and structural path context. */
    public static function at(string $source, string $path, string $message): self
    {
        return new self(\sprintf('Invalid cron configuration in %s at %s: %s', $source, $path, $message));
    }
}
