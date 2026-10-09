# Cron worker

Подключаемая PHP-библиотека для запуска задач по cron-правилам. Master-процесс
использует Revolt для неблокирующего ожидания таймеров и сигналов, а каждая
задача исполняется в отдельном процессе через `pcntl_fork()`.

Библиотека предназначена для Unix-like систем и требует PHP 8.2+, `pcntl` и
`posix`.

## Установка

```shell
composer require romanfedorskij/cron
```

## Использование

```php
<?php

declare(strict_types=1);

use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Schedule\Frequency\EveryTime;
use Wolfcharaa\Cron\Scheduler;

require __DIR__ . '/vendor/autoload.php';

$scheduler = new Scheduler(
    new SchedulerOptions(maxConcurrency: 4),
);

$scheduler
    ->task('cleanup', static function (): int {
        // Блокирующая работа безопасна для master event loop: этот код
        // выполняется в дочернем процессе.
        return 0;
    })
    ->everyMinutes(5)
    ->weekdays()
    ->timezone('Europe/Moscow')
    ->withoutOverlapping();

$scheduler->run();
```

Для обычных сценариев знать синтаксис cron не требуется:

```php
$scheduler->task('heartbeat', $handler)->everyMinute();
$scheduler->task('sync', $handler)->everyMinutes(15);
$scheduler->task('refresh', $handler)->hourlyAt(10);
$scheduler->task('report', $handler)->dailyAt(9, 30)->weekdays();
$scheduler->task('archive', $handler)->everyDays(3)->at(2);
$scheduler->task('billing', $handler)->monthly()->at(4, 15);
```

Интервалы представлены отдельными типами над абстрактным `EveryTime`. Их можно
передавать в builder явно, когда конфигурация создаётся отдельно:

```php
$interval = EveryTime::minutes(15);

$scheduler->task('sync', $handler)->every($interval);
```

Доступны интервалы в минутах, часах, днях и месяцах. Это календарные интервалы:
например, `everyMinutes(15)` преобразуется в `*/15 * * * *`, а
`everyHours(4)` — в `0 */4 * * *`.

## Профили запуска

Профиль — это именованный план задач для конкретного runtime, как отдельный
раздел `profiles.<name>` в cron-каталоге. Имя не ограничено окружениями:
подойдут `dev`, `stage`, `prod`, `default` или `server-10.11.166.100`.

Активный план выбирается один раз:

```php
$scheduler = new Scheduler(
    new SchedulerOptions(profile: 'server-10.11.166.100'),
);

$scheduler->task('shared', $handler); // any — входит во все планы
$scheduler
    ->task('ftp-upload', $handler)
    ->profiles('default', 'server-10.11.166.100');
```

Можно передать и собственный string-backed enum. `any` является wildcard и не
смешивается с именованными профилями одной задачи. Профиль применяется только к
корневой scheduled-задаче: после её запуска вся цепочка `then*()` исполняется
без повторной фильтрации и ожидания нового schedule.

## Низкоуровневый cron API

`CronExpression` можно создать из готовой строки или собрать по отдельным
правилам. Пустой объект соответствует запуску каждую минуту:

```php
$parsed = new CronExpression('15 2 * * MON-FRI');

$configured = (new CronExpression())
    ->withMinute(15)
    ->withHour(2)
    ->withDayOfWeek('MON-FRI');

echo $configured->toString(); // 15 2 * * MON-FRI
```

Объект неизменяемый: каждый метод `with*()` возвращает новый экземпляр. Строка
по-прежнему поддерживается как сокращённая запись:

```php
$scheduler->cron('legacy-task', '*/5 * * * *', $handler, 'Europe/Moscow');
```

## Задачи из контейнера

Для интеграции с PSR-11 передаётся фабрика, а не уже созданный контейнер. Она
вызывается после `fork` внутри процесса задачи, поэтому каждый запуск может
получить собственный scope и новые подключения:

```php
use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Scheduler;

$scheduler = new Scheduler(
    containerFactory: static function (): ContainerInterface {
        // Bootstrap приложения или создание нового container scope.
        return createApplicationContainer();
    },
);

$scheduler
    ->serviceTask('import', ImportCommand::class, 'run')
    ->everyMinutes(15)
    ->thenService('reindex', ReindexCommand::class, 'run')
    ->thenService('notify', NotifyCommand::class);
```

Если сервис реализует `__invoke()`, имя метода можно не указывать. Аргументы
метода передаются четвёртым параметром `serviceTask()` или `thenService()`.

Для произвольной логики доступен container-aware callback:

```php
use Wolfcharaa\Cron\Task\TaskExecutionContext;

$scheduler
    ->containerTask(
        'cleanup',
        static function (
            ContainerInterface $container,
            TaskExecutionContext $context,
        ): int {
            $container->get(CleanupService::class)->run();

            return 0;
        },
    )
    ->dailyAt(3);
```

Каждый элемент после `then()`, `thenContainer()` или `thenService()` является
одноразовым. Master ставит его во внутреннюю очередь только после успешного
завершения предыдущего процесса с exit code `0`. При исключении, сигнале или
ненулевом exit code цепочка останавливается.

`SchedulerInterface` предназначен для регистрации scheduler в DI-контейнере,
но не передаётся в child. Изменения объекта scheduler после `fork` остались бы
только в памяти дочернего процесса и не дошли бы до master.

Фабрика не должна возвращать контейнер master-процесса или захватывать из него
готовые сетевые клиенты. Она должна действительно создавать child-local scope.
Полную изоляцию уже открытых файловых дескрипторов обеспечит будущий адаптер
`fork + exec`; один `pcntl_fork()` не может отменить наследование памяти и FD.

Callable должен вернуть `null` или exit code от `0` до `255`. По умолчанию
повторный запуск задачи не начинается, пока работает её предыдущий процесс.
Глобальная параллельность ограничивается через `SchedulerOptions`.

> После fork дочерний процесс наследует память master-процесса. Не используйте
> одновременно в parent и child заранее открытые PDO, Redis и другие сетевые
> соединения. Создавайте process-local ресурсы через `containerFactory` или
> непосредственно внутри callable. Вариант
> `fork + exec` будет добавлен отдельным supervisor-адаптером.

## Сигналы

- `SIGCHLD` неблокирующе освобождает завершившиеся процессы через `WNOHANG`.
- `SIGTERM` и `SIGINT` останавливают планирование и начинают graceful shutdown.
- Повторный сигнал завершения или истечение grace period приводит к `SIGKILL`.

## Разработка

```shell
make check
```
