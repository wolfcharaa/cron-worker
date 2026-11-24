<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Discovery;

/** Supplies explicit class-strings to built-in attribute discovery. */
interface ClassProviderInterface
{
    /** @return iterable<class-string> */
    public function classes(): iterable;
}
