<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Discovery;

interface ClassProviderInterface
{
    /** @return iterable<class-string> */
    public function classes(): iterable;
}
