# Task chains

Success-chain — это последовательность одноразовых задач. Следующий шаг
ставится в очередь только после exit code `0` предыдущего. Он не ждёт своего
cron schedule и не становится новой scheduled-задачей.

## Fluent API

### Callable

```php
$scheduler
    ->task('first', static fn(): int => 0)
    ->everyMinutes(10)
    ->then('second', static fn(): int => 0);
```

### PSR-11 service

```php
$scheduler
    ->serviceTask('import', ImportCommand::class, 'run')
    ->everyMinutes(15)
    ->thenService('reindex', ReindexCommand::class, 'run', ['products']);
```

Service извлекается из свежего контейнера внутри child-процесса. Если method
не указан, service должен реализовывать `__invoke()`.

### Public static method

```php
final class SearchIndex
{
    public static function rebuild(string $index): int
    {
        return 0;
    }
}

$scheduler
    ->task('import', $handler)
    ->everyMinutes(15)
    ->thenStatic('reindex', SearchIndex::class, 'rebuild', ['products']);
```

Для корневой задачи доступен тот же контракт:

```php
$scheduler
    ->staticTask('reindex', SearchIndex::class, 'rebuild', ['products'])
    ->hourly();
```

Метод должен существовать, быть `public static`, принимать настроенные
позиционные аргументы и возвращать `int`, `null` или `void`.

### Container callback

```php
final class PipelineCallbacks
{
    public static function notify(
        ContainerInterface $container,
        TaskExecutionContext $context,
        string $channel,
    ): int {
        $container->get(Notifier::class)->send($channel, $context->taskName);

        return 0;
    }
}

$scheduler
    ->task('import', $handler)
    ->everyMinutes(15)
    ->thenContainer(
        'notify',
        [PipelineCallbacks::class, 'notify'],
        ['operations'],
    );
```

Первые два аргумента callback всегда `ContainerInterface` и
`TaskExecutionContext`. Настроенные аргументы передаются после них.

## ChainConfig в attributes

В attribute нельзя положить closure, поэтому цепочка использует декларативные
`TaskConfigInterface` объекты:

```php
use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\ContainerTaskConfig;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;
use Wolfcharaa\Cron\Configuration\StaticTaskConfig;

#[CronTask(
    schedule: '*/15 * * * *',
    profile: 'prod',
    method: 'run',
    chain: new ChainConfig(
        new ServiceTaskConfig(
            serviceId: ReindexCommand::class,
            method: 'run',
            taskName: 'reindex-service',
        ),
        new StaticTaskConfig(
            class: SearchIndex::class,
            method: 'rebuild',
            arguments: ['products'],
            taskName: 'reindex-static',
        ),
        new ContainerTaskConfig(
            class: PipelineCallbacks::class,
            method: 'notify',
            arguments: ['operations'],
            taskName: 'notify',
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
```

Доступные config-типы:

| Config | Выполнение |
| --- | --- |
| `ServiceTaskConfig` | service из свежего PSR-11 container |
| `StaticTaskConfig` | public static method без container |
| `ContainerTaskConfig` | public static callback с container/context |
| `ExecTaskConfig` | внешняя shell-команда |

Имена шагов внутри одного `ChainConfig` должны быть уникальны.

## Ошибки и остановка

Callable может вернуть `null` либо exit code `0..255`. Следующий шаг запускается
только после `0`. При исключении, сигнале или ненулевом коде остаток цепочки не
ставится в очередь.

Callable signatures и вся карта выбранного профиля проверяются до регистрации
задач. Подробности: [Configuration validation](../reference/configuration-validation.md).
