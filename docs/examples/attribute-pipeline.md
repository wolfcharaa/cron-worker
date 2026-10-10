# Attribute pipeline

Пример показывает profile-specific schedule с service, static и container
шагами одной success-chain.

```php
use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\ContainerTaskConfig;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Configuration\StaticTaskConfig;
use Wolfcharaa\Cron\Configuration\SymfonyCommandTaskConfig;
use Wolfcharaa\Cron\Configuration\SymfonyInput;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

#[CronTask(
    schedule: '*/15 * * * *',
    profiles: ['stage', 'prod'],
    name: 'import',
    method: 'run',
    chain: new ChainConfig(
        new ServiceTaskConfig(
            serviceId: ReindexCommand::class,
            method: 'run',
            taskName: 'reindex',
        ),
        new StaticTaskConfig(
            class: CacheWarmup::class,
            method: 'call',
            arguments: ['catalog'],
            taskName: 'warm-cache',
        ),
        new ContainerTaskConfig(
            class: PipelineCallbacks::class,
            method: 'notify',
            arguments: ['operations'],
            taskName: 'notify',
        ),
        new SymfonyCommandTaskConfig(
            commandId: PublishReportCommand::class,
            input: new SymfonyInput(
                arguments: ['report' => 'daily'],
                options: ['force' => true],
            ),
            taskName: 'publish-report',
        ),
    ),
)]
final class ImportCommand
{
    public function run(): int
    {
        return 0;
    }
}

final class CacheWarmup
{
    public static function call(string $namespace): int
    {
        return 0;
    }
}

final class PipelineCallbacks
{
    public static function notify(
        ContainerInterface $container,
        TaskExecutionContext $context,
        string $channel,
    ): int {
        $container->get(Notifier::class)->completed($channel, $context->taskName);

        return 0;
    }
}
```

`StaticTaskConfig` не создаёт container. `ContainerTaskConfig` и
`SymfonyCommandTaskConfig` получают новый container scope для своего
child-процесса. Если любой шаг вернёт ненулевой код, следующие шаги не будут
поставлены в очередь.
