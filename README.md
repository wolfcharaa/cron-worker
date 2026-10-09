# Cron worker

Подключаемая PHP-библиотека для запуска cron-задач в отдельных fork-процессах.
Master использует Revolt для неблокирующего ожидания таймеров и сигналов, а
блокирующая работа выполняется только внутри child-worker.

## Зачем это ставить

Библиотека полезна, когда приложению нужно:

- держать один long-running scheduler вместо отдельного процесса cron на каждую задачу;
- описывать расписания fluent API, атрибутами, YAML или обычным crontab;
- выбирать независимые планы `dev`, `stage`, `prod` или серверные профили;
- получать свежий PSR-11 container scope и новые подключения на каждый запуск;
- последовательно запускать одноразовые шаги только после exit code `0`;
- ограничивать пересечения и общее число одновременно работающих процессов;
- находить ошибки callable и конфигурации до запуска event loop.

## Что предоставляет библиотека

| Возможность | Для чего нужна | Подробности |
| --- | --- | --- |
| Fluent schedule API | `everyMinutes()`, `dailyAt()`, `weekdays()` без ручного cron | [Scheduling и profiles](docs/reference/scheduling-and-profiles.md) |
| Configuration loaders | Manual API, attributes, YAML и классический crontab | [Configuration sources](docs/guides/configuration-sources.md) |
| Success chains | Одноразовые callable, service, static, container и exec шаги | [Task chains](docs/guides/task-chains.md) |
| PSR-11 integration | Новый container scope внутри каждого child-worker | [Container contract](docs/reference/container-contract.md) |
| Preflight validation | Ошибки карты и callable до регистрации задач | [Configuration validation](docs/reference/configuration-validation.md) |
| Revolt runtime | Таймеры, сигналы и неблокирующий master process | [Runtime](docs/reference/runtime.md) |
| Worker bootstrap | Явная композиция loaders и приложения в `bin/cron.php` | [Application worker](docs/examples/application-worker.md) |
| Crontab conversion | Перенос `crontab -l` в управляемый YAML | [Configuration sources](docs/guides/configuration-sources.md#классический-crontab) |

## Общая модель

```text
manual / attributes / YAML / crontab
                 |
                 v
          CronProfileMap
                 |
                 v
       preflight validation
                 |
                 v
      Revolt scheduler master
                 |
                 v
        forked task process
                 |
          exit code == 0
                 |
                 v
       next one-shot task
```

Расписание принадлежит только корневой задаче. Шаги `then*()` не получают
собственный cron и ставятся во внутреннюю очередь лишь после успешного
завершения предыдущего процесса.

## Что остаётся на стороне приложения

Библиотека планирует и исполняет задачи, но не заменяет framework, DI container
или process supervisor. Приложение определяет:

- какие команды и services существуют;
- как создаётся свежий PSR-11 container;
- какой профиль активен на конкретном runtime;
- где хранятся YAML/crontab файлы;
- как scheduler запускается и перезапускается через systemd, Docker или supervisor;
- куда отправляются lifecycle-логи.

## Как читать документацию

1. [Quick start](docs/guides/quick-start.md) — первый ручной scheduler.
2. [Configuration sources](docs/guides/configuration-sources.md) — выбор loader-а и profiles.
3. [Task chains](docs/guides/task-chains.md) — service/static/container pipelines.
4. [Application worker](docs/examples/application-worker.md) — полный `bin/cron.php`.
5. [Runtime](docs/reference/runtime.md) — fork, Revolt, overlap и shutdown.
6. [Configuration validation](docs/reference/configuration-validation.md) — fail-fast контракт.

## Установка

```shell
composer require romanfedorskij/cron
```

Требования:

- PHP `^8.2` на Unix-like системе;
- `ext-pcntl` и `ext-posix`;
- Revolt event loop;
- любой PSR-11 container нужен только для service/container задач.

## Quick start

```php
<?php

declare(strict_types=1);

use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Scheduler;

require __DIR__ . '/vendor/autoload.php';

$scheduler = new Scheduler(
    new SchedulerOptions(maxConcurrency: 4, profile: 'default'),
);

$scheduler
    ->task('cleanup', static function (): int {
        // Код выполняется в child-worker и не блокирует master event loop.
        return 0;
    })
    ->everyMinutes(5)
    ->weekdays()
    ->timezone('Europe/Moscow')
    ->withoutOverlapping();

$scheduler->run();
```

Полный разбор: [docs/guides/quick-start.md](docs/guides/quick-start.md).

## Chain quick start

```php
$scheduler
    ->serviceTask('import', ImportCommand::class, 'run')
    ->everyMinutes(15)
    ->thenStatic('reindex', SearchIndex::class, 'rebuild', ['products'])
    ->thenContainer(
        'notify',
        [PipelineCallbacks::class, 'notify'],
        ['operations'],
    );
```

Static callback для `thenContainer()` получает container и context перед
настроенными аргументами:

```php
public static function notify(
    ContainerInterface $container,
    TaskExecutionContext $context,
    string $channel,
): int {
    return 0;
}
```

В атрибутах те же шаги задаются через `StaticTaskConfig` и
`ContainerTaskConfig`. См. [полный пример attribute pipeline](docs/examples/attribute-pipeline.md).

## Документация по разделам

### Guides

- [Quick start](docs/guides/quick-start.md)
- [Configuration sources](docs/guides/configuration-sources.md)
- [Task chains](docs/guides/task-chains.md)

### Reference

- [Scheduling и profiles](docs/reference/scheduling-and-profiles.md)
- [Runtime](docs/reference/runtime.md)
- [Container contract](docs/reference/container-contract.md)
- [Configuration validation](docs/reference/configuration-validation.md)

### Examples

- [Application worker](docs/examples/application-worker.md)
- [Attribute pipeline](docs/examples/attribute-pipeline.md)
- Исполняемые PHP-заготовки находятся в [`examples/`](examples/).

## Разработка

```shell
make check
make test-php-matrix
```

Матрица проверяет PHP 8.2–8.5 с минимально допустимыми и последними
совместимыми версиями зависимостей. Один вариант можно запустить отдельно:

```shell
make test-php-version PHP_VERSION=8.4 DEPENDENCY_MODE=lowest
```
