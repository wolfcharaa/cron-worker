<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration\Builder;

use Wolfcharaa\Cron\Discovery\AttributeCronTaskDiscovery;
use Wolfcharaa\Cron\Discovery\ClassProviderInterface;
use Wolfcharaa\Cron\Discovery\ComposerProjectClassProvider;
use Wolfcharaa\Cron\Registry\CronProfileMap;

final readonly class AttributeCronConfigBuilder implements CronConfigBuilderInterface
{
    public static function forProject(?string $projectRoot = null, bool $includeDev = false): self
    {
        $projectRoot ??= getcwd();

        if ($projectRoot === false || $projectRoot === '') {
            throw new \RuntimeException('Unable to determine the Composer project root.');
        }

        return new self(new ComposerProjectClassProvider($projectRoot, $includeDev));
    }

    public function __construct(private ClassProviderInterface $classes) {}

    public function build(): CronProfileMap
    {
        return (new AttributeCronTaskDiscovery())->discover($this->classes);
    }
}
