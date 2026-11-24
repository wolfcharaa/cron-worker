<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Discovery;

/** Supplies a validated explicit list of existing classes. */
final readonly class ClassListProvider implements ClassProviderInterface
{
    /** @param list<class-string> $classes */
    public function __construct(private array $classes)
    {
        foreach ($this->classes as $class) {
            if (trim($class) === '' || !class_exists($class)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Cron discovery class "%s" must be an existing class-string.',
                    $class,
                ));
            }
        }
    }

    /** Yields validated classes in constructor order. */
    public function classes(): iterable
    {
        yield from $this->classes;
    }
}
