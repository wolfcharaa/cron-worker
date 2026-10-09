<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Exception;

final class InvalidTaskResult extends \UnexpectedValueException
{
    public function __construct(string $taskName, mixed $result)
    {
        parent::__construct(\sprintf(
            'Task "%s" must return null or an exit code between 0 and 255; %s returned.',
            $taskName,
            get_debug_type($result),
        ));
    }
}
