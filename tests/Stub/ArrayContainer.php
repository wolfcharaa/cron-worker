<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Stub;

use Psr\Container\ContainerInterface;

final readonly class ArrayContainer implements ContainerInterface
{
    /** @param array<string, mixed> $services */
    public function __construct(private array $services) {}

    public function get(string $id): mixed
    {
        if (!\array_key_exists($id, $this->services)) {
            throw new \RuntimeException(\sprintf('Service "%s" was not found.', $id));
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}
