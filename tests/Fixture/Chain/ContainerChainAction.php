<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Chain;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

final class ContainerChainAction
{
    public static function call(
        ContainerInterface $container,
        TaskExecutionContext $context,
        int $increment,
    ): int {
        $value = $container->get('value');

        if (!\is_int($value)) {
            throw new \UnexpectedValueException('Expected an integer container value.');
        }

        return $context->taskName === 'container-step' ? $value + $increment : 1;
    }

    public static function missingContext(ContainerInterface $container): int
    {
        return $container->has('value') ? 0 : 1;
    }
}
