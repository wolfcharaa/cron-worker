<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Exception;

final class MissingContainerFactory extends \LogicException
{
    public function __construct()
    {
        parent::__construct(
            'Container-aware tasks require a ContainerFactory or container factory callable.',
        );
    }
}
