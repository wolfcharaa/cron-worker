<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Discovery;

final readonly class ChainClassProvider implements ClassProviderInterface
{
    /** @var list<ClassProviderInterface> */
    private array $providers;

    public function __construct(ClassProviderInterface ...$providers)
    {
        $this->providers = array_values($providers);
    }

    public function classes(): iterable
    {
        $seen = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->classes() as $class) {
                if (isset($seen[$class])) {
                    continue;
                }

                $seen[$class] = true;
                yield $class;
            }
        }
    }
}
