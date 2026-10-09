<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Compatibility;

use Testo\Assert;
use Testo\Test;

#[Test]
final class RuntimePlatformTest
{
    public function supportsForkedSchedulerRuntime(): void
    {
        Assert::true(\extension_loaded('pcntl'));
        Assert::true(\extension_loaded('posix'));
        Assert::true(\function_exists('pcntl_fork'));
        Assert::true(\function_exists('pcntl_waitpid'));
    }
}
