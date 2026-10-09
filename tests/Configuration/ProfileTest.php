<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Profile;

#[Test]
final class ProfileTest
{
    public function acceptsArbitraryCatalogProfileNames(): void
    {
        $profile = new Profile('server-10.11.166.100');

        Assert::same($profile->name, 'server-10.11.166.100');
        Assert::same((string) $profile, 'server-10.11.166.100');
    }

    public function acceptsStringBackedEnums(): void
    {
        Assert::same(Profile::normalize(TestProfile::Stage)->name, 'stage');
    }

    public function anyIncludesEveryActiveProfile(): void
    {
        Assert::true((new Profile(Profile::ANY))->includes(new Profile('prod')));
        Assert::true((new Profile('prod'))->includes(new Profile('prod')));
        Assert::false((new Profile('stage'))->includes(new Profile('prod')));
    }

    public function rejectsInvalidProfileName(): void
    {
        Expect::exception(\InvalidArgumentException::class);
        new Profile('server profile');
    }
}

enum TestProfile: string
{
    case Stage = 'stage';
}
