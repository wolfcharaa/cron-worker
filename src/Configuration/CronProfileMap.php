<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;

/** Complete immutable set of task declarations returned by configuration loaders. */
final readonly class CronProfileMap
{
    /** @var list<CronTaskDefinition> */
    private array $definitionList;

    /** @var array<string, list<CronTaskDefinition>> */
    private array $byProfile;

    /** @param iterable<CronTaskDefinition> $definitions */
    public function __construct(iterable $definitions = [])
    {
        $definitionList = [];
        $byProfile = [];
        $names = [];

        foreach ($definitions as $definition) {
            $definitionList[] = $definition;

            foreach ($definition->profiles as $profile) {
                if (isset($names[$profile->name][$definition->name])) {
                    throw self::duplicate($definition->name, $profile->name);
                }

                foreach ($profile->name === Profile::ANY ? array_keys($byProfile) : [$profile->name] as $name) {
                    if ($name !== Profile::ANY && isset($names[$name][$definition->name])) {
                        throw self::duplicate($definition->name, $name);
                    }
                }

                if ($profile->name !== Profile::ANY && isset($names[Profile::ANY][$definition->name])) {
                    throw self::duplicate($definition->name, $profile->name);
                }

                $names[$profile->name][$definition->name] = true;
                $byProfile[$profile->name][] = $definition;
            }
        }

        $this->definitionList = $definitionList;
        $this->byProfile = $byProfile;
    }

    /**
     * Returns declarations once in source order.
     *
     * @return list<CronTaskDefinition>
     */
    public function definitions(): array
    {
        return $this->definitionList;
    }

    /**
     * Returns declared profile names in first-seen order.
     *
     * @return list<string>
     */
    public function profiles(): array
    {
        return array_keys($this->byProfile);
    }

    /**
     * Resolves shared and named declarations without changing the map.
     *
     * @return list<CronTaskDefinition>
     */
    public function forProfile(Profile|string|\BackedEnum $profile): array
    {
        $profile = Profile::normalize($profile);
        $shared = $this->byProfile[Profile::ANY] ?? [];

        if ($profile->name === Profile::ANY) {
            return $shared;
        }

        return [...$shared, ...($this->byProfile[$profile->name] ?? [])];
    }

    private static function duplicate(string $task, string $profile): InvalidCronConfiguration
    {
        return InvalidCronConfiguration::at(
            '<definitions>',
            'profiles.' . $profile . '.tasks.' . $task,
            \sprintf('task "%s" is declared more than once for the resolved profile.', $task),
        );
    }
}
