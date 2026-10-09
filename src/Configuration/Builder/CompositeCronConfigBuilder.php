<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Builder;

use Wolfcharaa\Cron\Registry\CronProfileMap;

final readonly class CompositeCronConfigBuilder implements CronConfigBuilderInterface
{
    /** @var list<CronConfigBuilderInterface> */
    private array $builders;

    public function __construct(CronConfigBuilderInterface ...$builders)
    {
        $this->builders = array_values($builders);
    }

    public function build(): CronProfileMap
    {
        $map = new CronProfileMap();

        foreach ($this->builders as $builder) {
            $map->merge($builder->build());
        }

        return $map;
    }
}
