# Container contract

Scheduler принимает `ContainerFactory` или callable, который возвращает
`Psr\Container\ContainerInterface`.

```php
use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Scheduler;

$scheduler = new Scheduler(
    containerFactory: static function (): ContainerInterface {
        return createApplicationContainer();
    },
);
```

Factory сохраняется в master, но вызывается handler-ом только после `fork`.
Каждый запуск может получить новый scope, PDO, Redis client и другие
process-local resources.

## Service task

```php
$scheduler
    ->serviceTask('import', ImportCommand::class, 'run', ['prepared'])
    ->everyMinutes(15)
    ->thenService('notify', NotifyCommand::class);
```

`serviceId` может быть class name или opaque PSR-11 id. Если method не указан,
service должен быть invokable.

## Container callback

```php
$scheduler
    ->containerTask(
        'cleanup',
        static function (
            ContainerInterface $container,
            TaskExecutionContext $context,
            int $limit,
        ): int {
            $container->get(CleanupService::class)->run($limit);

            return 0;
        },
        [100],
    )
    ->dailyAt(3);
```

Callback получает container, execution context и затем настроенные позиционные
arguments. Для declarative `ChainConfig` используйте public static callback и
`ContainerTaskConfig`.

## Важные границы

- Factory не должна возвращать уже созданный master container или захватывать
  открытые соединения.
- `SchedulerInterface` не передаётся в child: изменения scheduler после fork
  остались бы только в памяти child-процесса.
- Preflight validator не вызывает factory в master и не делает `container->get()`.
  Поэтому существование opaque service id проверяет сам container при запуске.
- Для YAML/crontab exec-задач container вообще не обязателен.
