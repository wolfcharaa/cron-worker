<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Task;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Configuration\Loader\AttributeCronConfigLoader;
use Wolfcharaa\Cron\Configuration\SymfonyCommandTaskConfig;
use Wolfcharaa\Cron\Container\CallableContainerFactory;
use Wolfcharaa\Cron\Discovery\ClassListProvider;
use Wolfcharaa\Cron\Task\TaskExecutionContext;
use Wolfcharaa\Cron\Tests\Fixture\Symfony\FixtureHelper;
use Wolfcharaa\Cron\Tests\Fixture\Symfony\ScheduledSymfonyCommand;
use Wolfcharaa\Cron\Tests\Stub\ArrayContainer;

#[Test]
final class SymfonyCommandTaskHandlerTest
{
    public function discoversAndRunsSymfonyCommandWithChildContainerServices(): void
    {
        $command = new ScheduledSymfonyCommand();
        $output = new BufferedOutput();
        $containerCreations = 0;
        $containerFactory = new CallableContainerFactory(
            static function () use ($command, $output, &$containerCreations): ContainerInterface {
                ++$containerCreations;

                return new ArrayContainer([
                    ScheduledSymfonyCommand::class => $command,
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
        Assert::same($containerCreations, 0);

        $now = new \DateTimeImmutable();
        $result = $definition->task->createHandler($containerFactory)->execute(
            new TaskExecutionContext($definition->name, $now, $now),
        );

        Assert::same($result, 0);
        Assert::same($containerCreations, 1);
        Assert::same($command->receivedScope, 'expired');
        Assert::true($command->receivedHelper);
        Assert::same(trim($output->fetch()), 'scope=expired');
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
            ['temporary'],
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
            ['temporary'],
        ))->createHandler($factory);
        $now = new \DateTimeImmutable();

        Expect::exception(\UnexpectedValueException::class);
        $handler->execute(new TaskExecutionContext('cleanup', $now, $now));
    }
}
