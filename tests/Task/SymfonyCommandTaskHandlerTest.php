<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Task;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Loader\AttributeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\SymfonyCommandTaskConfig;
use Wolfcharaa\Cron\Configuration\SymfonyInput;
use Wolfcharaa\Cron\Container\CallableContainerFactory;
use Wolfcharaa\Cron\Discovery\ClassListProvider;
use Wolfcharaa\Cron\Task\TaskExecutionContext;
use Wolfcharaa\Cron\Tests\Fixture\Symfony\FixtureHelper;
use Wolfcharaa\Cron\Tests\Fixture\Symfony\FollowUpSymfonyCommand;
use Wolfcharaa\Cron\Tests\Fixture\Symfony\ScheduledSymfonyCommand;
use Wolfcharaa\Cron\Tests\Stub\ArrayContainer;

#[Test]
final class SymfonyCommandTaskHandlerTest
{
    public function discoversAndRunsSymfonyCommandWithChildContainerServices(): void
    {
        $command = new ScheduledSymfonyCommand();
        $followUp = new FollowUpSymfonyCommand();
        $output = new BufferedOutput();
        $containerCreations = 0;
        $containerFactory = new CallableContainerFactory(
            static function () use ($command, $followUp, $output, &$containerCreations): ContainerInterface {
                ++$containerCreations;

                return new ArrayContainer([
                    ScheduledSymfonyCommand::class => $command,
                    FollowUpSymfonyCommand::class => $followUp,
                    HelperSet::class => new HelperSet([new FixtureHelper()]),
                    OutputInterface::class => $output,
                ]);
            },
        );
        $map = (new AttributeCronConfigLoader(new ClassListProvider([
            ScheduledSymfonyCommand::class,
        ])))->load();
        $definition = $map->forProfile('stage')[0] ?? null;

        if ($definition === null) {
            throw new \LogicException('Symfony task was not discovered.');
        }

        Assert::same(
            $definition,
            $map->forProfile('prod')[0] ?? null,
        );
        Assert::true($definition->task instanceof SymfonyCommandTaskConfig);
        Assert::same($definition->expression->toString(), '*/5 * * * *');
        Assert::same($containerCreations, 0);

        $now = new \DateTimeImmutable();
        $result = $definition->task->createHandler($containerFactory)->execute(
            new TaskExecutionContext($definition->name, $now, $now),
        );

        Assert::same($result, 0);
        Assert::same($containerCreations, 1);
        Assert::same($command->receivedScope, 'expired');
        Assert::same($command->receivedLimit, 50);
        Assert::true($command->receivedForce);
        Assert::true($command->receivedHelper);
        Assert::false($command->receivedInteractive);
        Assert::same(trim($output->fetch()), 'scope=expired');

        $followUpConfig = $definition->chain->tasks()[0] ?? null;

        if (!$followUpConfig instanceof SymfonyCommandTaskConfig) {
            throw new \LogicException('Symfony follow-up task was not discovered.');
        }

        Assert::same(
            $followUpConfig->createHandler($containerFactory)->execute(
                new TaskExecutionContext($followUpConfig->name(), $now, $now),
            ),
            0,
        );
        Assert::same($containerCreations, 2);
        Assert::same($followUp->receivedMessage, 'DONE');
        Assert::same(trim($output->fetch()), 'message=DONE');
    }

    public function instantiatesSimpleCommandWhenContainerDoesNotRegisterIt(): void
    {
        $output = new BufferedOutput();
        $factory = new CallableContainerFactory(
            static fn(): ContainerInterface => new ArrayContainer([
                OutputInterface::class => $output,
            ]),
        );
        $handler = (new SymfonyCommandTaskConfig(
            ScheduledSymfonyCommand::class,
            new SymfonyInput(arguments: ['scope' => 'temporary']),
        ))->createHandler($factory);
        $now = new \DateTimeImmutable();

        Assert::same($handler->execute(new TaskExecutionContext('cleanup', $now, $now)), 0);
        Assert::same(trim($output->fetch()), 'scope=temporary');
    }

    public function rejectsInvalidOutputServiceExplicitly(): void
    {
        $factory = new CallableContainerFactory(
            static fn(): ContainerInterface => new ArrayContainer([
                OutputInterface::class => new \stdClass(),
            ]),
        );
        $handler = (new SymfonyCommandTaskConfig(
            ScheduledSymfonyCommand::class,
            new SymfonyInput(arguments: ['scope' => 'temporary']),
        ))->createHandler($factory);
        $now = new \DateTimeImmutable();

        Expect::exception(\UnexpectedValueException::class);
        $handler->execute(new TaskExecutionContext('cleanup', $now, $now));
    }

    public function rejectsInputUnknownToTheCommandDefinition(): void
    {
        $factory = new CallableContainerFactory(
            static fn(): ContainerInterface => new ArrayContainer([]),
        );
        $handler = (new SymfonyCommandTaskConfig(
            ScheduledSymfonyCommand::class,
            new SymfonyInput(options: ['missing' => true]),
        ))->createHandler($factory);
        $now = new \DateTimeImmutable();

        Expect::exception(InvalidOptionException::class);
        $handler->execute(new TaskExecutionContext('cleanup', $now, $now));
    }
}
