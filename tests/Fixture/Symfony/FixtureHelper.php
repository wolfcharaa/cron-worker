<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Symfony;

use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Helper\HelperSet;

final class FixtureHelper implements HelperInterface
{
    public function setHelperSet(?HelperSet $helperSet): void {}

    public function getHelperSet(): ?HelperSet
    {
        return null;
    }

    public function getName(): string
    {
        return 'fixture';
    }
}
