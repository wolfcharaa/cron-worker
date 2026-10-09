<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Discovery;

final readonly class RegexFilterClassProvider implements ClassProviderInterface
{
    public function __construct(
        private ClassProviderInterface $inner,
        private string $pattern,
    ) {}

    public function classes(): iterable
    {
        foreach ($this->inner->classes() as $class) {
            if (preg_match($this->pattern, $class) === 1) {
                yield $class;
            }
        }
    }
}
