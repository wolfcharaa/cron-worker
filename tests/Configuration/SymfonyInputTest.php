<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\SymfonyInput;

#[Test]
final class SymfonyInputTest
{
    public function buildsNamedArgumentsAndLongOptions(): void
    {
        $input = new SymfonyInput(
            arguments: [
                'scope' => 'expired',
                'ids' => [10, 20],
            ],
            options: [
                'limit' => 50,
                'force' => true,
            ],
        );

        Assert::same($input->parameters(), [
            'scope' => 'expired',
            'ids' => [10, 20],
            '--limit' => 50,
            '--force' => true,
        ]);
    }

    public function rejectsPositionalArguments(): void
    {
        Expect::exception(\InvalidArgumentException::class);
        new SymfonyInput(arguments: ['expired']);
    }

    public function rejectsOptionPrefixes(): void
    {
        Expect::exception(\InvalidArgumentException::class);
        new SymfonyInput(options: ['--force' => true]);
    }
}
