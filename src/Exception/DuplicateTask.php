<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Exception;

final class DuplicateTask extends \LogicException
{
    public function __construct(string $taskName)
    {
        parent::__construct(\sprintf('A task named "%s" is already registered.', $taskName));
    }
}
