<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Discovery;

final readonly class ClassListProvider implements ClassProviderInterface
{
    /** @param list<class-string> $classes */
    public function __construct(private array $classes) {}

    public function classes(): iterable
    {
        yield from $this->classes;
    }
}
