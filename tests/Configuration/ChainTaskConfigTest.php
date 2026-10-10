<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Configuration;

use Psr\Container\ContainerInterface;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\ContainerTaskConfig;
use Wolfcharaa\Cron\Configuration\ExecTaskConfig;
use Wolfcharaa\Cron\Configuration\Loader\AttributeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\StaticTaskConfig;
use Wolfcharaa\Cron\Container\CallableContainerFactory;
use Wolfcharaa\Cron\Discovery\ClassListProvider;
use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\Registry\CronProfileMap;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Scheduler;
use Wolfcharaa\Cron\Task\TaskExecutionContext;
use Wolfcharaa\Cron\Tests\Fixture\Chain\ConfiguredChainCommand;
use Wolfcharaa\Cron\Tests\Fixture\Chain\ContainerChainAction;
use Wolfcharaa\Cron\Tests\Fixture\Chain\StaticChainAction;
use Wolfcharaa\Cron\Tests\Stub\ArrayContainer;
use Wolfcharaa\Cron\Validation\CronConfigValidator;

#[Test]
final class ChainTaskConfigTest
{
    public function buildsStaticAndContainerStepsFromAttributeConfig(): void
    {
        $scheduler = new Scheduler(
            options: new SchedulerOptions(profile: 'test'),
            containerFactory: static fn(): ContainerInterface => new ArrayContainer([
                ConfiguredChainCommand::class => new ConfiguredChainCommand(),
                'value' => 20,
            ]),
        );
        $scheduler->load(new AttributeCronConfigLoader(
            new ClassListProvider([ConfiguredChainCommand::class]),
        ));
        $task = $scheduler->tasks()['configured-chain']
            ?? throw new \LogicException('Configured chain is missing.');
        $static = $task->next ?? throw new \LogicException('Static chain step is missing.');
        $container = $static->next ?? throw new \LogicException('Container chain step is missing.');
        $context = new TaskExecutionContext(
            taskName: 'container-step',
            plannedAt: new \DateTimeImmutable(),
            startedAt: new \DateTimeImmutable(),
        );

        Assert::same($static->name, 'static-step');
        Assert::same($static->execute(), 7);
        Assert::same($container->name, 'container-step');
        Assert::same($container->execute($context), 23);
    }

    public function reportsAllInvalidChainCallablesBeforeSchedulerRegistration(): void
    {
        $map = new CronProfileMap([
            new CronTaskDefinition(
                task: new ExecTaskConfig('root', 'true'),
                expression: '* * * * *',
                profiles: 'prod',
                chain: new ChainConfig(
                    new StaticTaskConfig(
                        StaticChainAction::class,
                        'instanceMethod',
                        taskName: 'not-static',
                    ),
                    new StaticTaskConfig(
                        StaticChainAction::class,
                        'invalidResult',
                        taskName: 'invalid-result',
                    ),
                    new ContainerTaskConfig(
                        ContainerChainAction::class,
                        'missingContext',
                        taskName: 'missing-context',
                    ),
                ),
            ),
        ]);
        $factory = new CallableContainerFactory(
            static fn(): ContainerInterface => new ArrayContainer(['value' => 20]),
        );
        $result = (new CronConfigValidator())->validate($map, $factory, 'prod');
        $notStatic = $result->issues[0] ?? throw new \LogicException('Expected not-static issue.');
        $invalidResult = $result->issues[1] ?? throw new \LogicException('Expected invalid-result issue.');
        $missingContext = $result->issues[2] ?? throw new \LogicException('Expected missing-context issue.');

        Assert::same(\count($result->issues), 3);
        Assert::same($notStatic->task, 'not-static');
        Assert::same($invalidResult->task, 'invalid-result');
        Assert::same($missingContext->task, 'missing-context');

        $scheduler = new Scheduler(
            options: new SchedulerOptions(profile: 'prod'),
            containerFactory: $factory,
        );

        Expect::exception(InvalidCronConfiguration::class);
        $scheduler->load($map);
    }

    public function reportsMissingContainerFactoryBeforeBuildingChain(): void
    {
        $map = new CronProfileMap([
            new CronTaskDefinition(
                task: new ExecTaskConfig('root', 'true'),
                expression: '* * * * *',
                chain: new ChainConfig(
                    new ContainerTaskConfig(
                        ContainerChainAction::class,
                        'call',
                        arguments: [3],
                        taskName: 'container-step',
                    ),
                ),
            ),
        ]);
        $result = (new CronConfigValidator())->validate($map, profile: 'default');
        $issue = $result->issues[0] ?? throw new \LogicException('Expected missing-container issue.');

        Assert::same(\count($result->issues), 1);
        Assert::same($issue->task, 'container-step');
        Assert::true(str_contains($issue->message, 'Container-aware tasks require'));
    }
}
