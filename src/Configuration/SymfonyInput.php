<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

final readonly class SymfonyInput
{
    /** @var array<string, mixed> */
    public array $arguments;

    /** @var array<string, mixed> */
    public array $options;

    /**
     * @param array<array-key, mixed> $arguments
     * @param array<array-key, mixed> $options
     */
    public function __construct(
        array $arguments = [],
        array $options = [],
    ) {
        $this->assertNames($arguments, 'argument');
        $this->assertNames($options, 'option');

        /** @var array<string, mixed> $arguments */
        $this->arguments = $arguments;
        /** @var array<string, mixed> $options */
        $this->options = $options;
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        $parameters = $this->arguments;

        foreach ($this->options as $name => $value) {
            $parameters['--' . $name] = $value;
        }

        return $parameters;
    }

    /** @param array<array-key, mixed> $values */
    private function assertNames(array $values, string $type): void
    {
        foreach ($values as $name => $_value) {
            if (!\is_string($name) || $name === '') {
                throw new \InvalidArgumentException(\sprintf(
                    'Symfony input %s names must be non-empty strings.',
                    $type,
                ));
            }

            if (str_starts_with($name, '-')) {
                throw new \InvalidArgumentException(\sprintf(
                    'Symfony input %s "%s" must be declared without leading hyphens.',
                    $type,
                    $name,
                ));
            }
        }
    }
}
