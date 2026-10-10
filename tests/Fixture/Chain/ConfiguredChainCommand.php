<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Fixture\Chain;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\ContainerTaskConfig;
use Wolfcharaa\Cron\Configuration\StaticTaskConfig;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

#[CronTask(
    schedule: '*/5 * * * *',
    profiles: 'test',
    name: 'configured-chain',
    chain: new ChainConfig(
        new StaticTaskConfig(
            class: StaticChainAction::class,
            method: 'call',
            arguments: [7],
            taskName: 'static-step',
        ),
        new ContainerTaskConfig(
            class: ContainerChainAction::class,
            method: 'call',
            arguments: [3],
            taskName: 'container-step',
        ),
    ),
)]
final class ConfiguredChainCommand
{
    public function __invoke(): int
    {
        return 0;
    }
}

final class StaticChainAction
{
    public static function call(int $exitCode): int
    {
        return $exitCode;
    }

    public function instanceMethod(): int
    {
        return 0;
    }

    public static function invalidResult(): string
    {
        return 'invalid';
    }
}

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
