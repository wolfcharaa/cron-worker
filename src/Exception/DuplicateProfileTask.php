<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Exception;

final class DuplicateProfileTask extends \LogicException
{
    public function __construct(string $taskName, string $profile)
    {
        parent::__construct(\sprintf(
            'A task named "%s" is already registered in cron profile "%s".',
            $taskName,
            $profile,
        ));
    }
}
