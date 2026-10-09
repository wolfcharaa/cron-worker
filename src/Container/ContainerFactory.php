<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Container;

use Psr\Container\ContainerInterface;

interface ContainerFactory
{
    public function create(): ContainerInterface;
}
