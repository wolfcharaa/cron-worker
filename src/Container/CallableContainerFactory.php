<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Container;

use Psr\Container\ContainerInterface;

final readonly class CallableContainerFactory implements ContainerFactory
{
    /** @var \Closure(): ContainerInterface */
    private \Closure $factory;

    /** @param callable(): ContainerInterface $factory */
    public function __construct(callable $factory)
    {
        $this->factory = \Closure::fromCallable($factory);
    }

    public function create(): ContainerInterface
    {
        return ($this->factory)();
    }
}
