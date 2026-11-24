<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

/** Validated scheduler deployment-profile name. */
final readonly class Profile implements \Stringable
{
    public const ANY = 'any';
    public const DEFAULT = 'default';

    public string $name;

    public function __construct(string|\BackedEnum $name)
    {
        $name = $name instanceof \BackedEnum ? $name->value : $name;

        if (!\is_string($name)) {
            throw new \InvalidArgumentException('Scheduler profile enum must have a string value.');
        }

        $name = trim($name);

        if ($name === '' || preg_match('/^[A-Za-z0-9_.-]+$/D', $name) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid scheduler profile "%s". Use letters, digits, dots, underscores and hyphens.',
                $name,
            ));
        }

        $this->name = $name;
    }

    /** Normalizes one public profile representation. */
    public static function normalize(self|string|\BackedEnum $profile): self
    {
        return $profile instanceof self ? $profile : new self($profile);
    }

    /**
     * @param self|string|\BackedEnum|list<self|string|\BackedEnum> $profiles
     * @return list<self>
     */
    public static function normalizeMany(self|string|\BackedEnum|array $profiles): array
    {
        return self::normalizeList(\is_array($profiles) ? $profiles : [$profiles]);
    }

    /**
     * @param list<self|string|\BackedEnum> $profiles
     * @return list<self>
     */
    public static function normalizeList(array $profiles): array
    {
        if ($profiles === []) {
            throw new \InvalidArgumentException('At least one scheduler profile is required.');
        }

        $normalized = [];

        foreach ($profiles as $profile) {
            $profile = self::normalize($profile);
            $normalized[$profile->name] = $profile;
        }

        if (isset($normalized[self::ANY]) && \count($normalized) > 1) {
            throw new \InvalidArgumentException('Profile "any" cannot be combined with named profiles.');
        }

        return array_values($normalized);
    }

    /** Reports whether this declaration includes the active profile. */
    public function includes(self $active): bool
    {
        return $this->name === self::ANY || $this->name === $active->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
