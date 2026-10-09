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

### Ограничения параллельности

`OverlapPolicy` действует на одну задачу по имени. Значение `Forbid`, которое
используется по умолчанию, не запускает новый экземпляр `cleanup`, пока другой
`cleanup` ещё работает. `Allow` разрешает такие пересечения и включается через
`allowOverlapping()` или соответствующее поле конфигурации.

`SchedulerOptions::maxConcurrency` — общий предел одновременно работающих
fork-worker процессов для всего scheduler. Когда предел занят, очередная
scheduled-задача пропускает текущий cron tick, а одноразовая задача из
`ChainConfig` остаётся во внутренней очереди до освобождения worker slot.

Эти параметры не делают выполнение асинхронным и не блокируют event loop. Это
защита от повторной неидемпотентной работы и исчерпания CPU, памяти, соединений
или файловых дескрипторов. Неблокирующее ожидание обеспечивают таймеры Revolt,
`SIGCHLD` и `waitpid(..., WNOHANG)` в master-процессе.

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
$configuredProfile = getenv('APP_CRON_PROFILE');
$activeProfile = is_string($configuredProfile) && $configuredProfile !== ''
    ? $configuredProfile
    : 'default';

$scheduler = new Scheduler(
    new SchedulerOptions(profile: $activeProfile),
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

### Атрибуты команд

Карта профилей может собираться из repeatable-атрибутов над сервисами команд:

```php
use Wolfcharaa\Cron\Attribute\CronTask;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\ServiceTaskConfig;

#[CronTask(
    schedule: '*/10 * * * *',
    profile: 'default',
    name: 'fis-upload',
    method: 'run',
    chain: new ChainConfig(
        new ServiceTaskConfig(
            serviceId: ReindexPackages::class,
            method: 'run',
            taskName: 'fis-reindex',
        ),
        new ServiceTaskConfig(
            serviceId: NotifyOperator::class,
            taskName: 'fis-notify',
        ),
    ),
)]
#[CronTask(
    schedule: '*/10 * * * *',
    profile: 'server-10.11.166.100',
    name: 'fis-upload',
    method: 'run',
)]
#[CronTask(
    schedule: '0 * * * *',
    profile: 'server-10.11.159.86',
    name: 'fis-upload',
    method: 'run',
)]
final class UploadPreparedPackages
{
    public function run(): int
    {
        return 0;
    }
}
```

Каждый атрибут принадлежит ровно одному профилю. Для другой конфигурации той же
команды добавляется ещё один атрибут: расписание, timezone и overlap policy
остаются полностью независимыми между планами. Общую задачу можно явно отнести
к профилю `any`.

`ChainConfig` задаёт одноразовый success-pipeline именно для этого атрибута.
Каждый следующий сервис запускается отдельным worker-процессом только после
exit code `0` предыдущего; его cron-расписание для этого не требуется.

Какие классы обходить, решает бинарная команда приложения. Например,
`bin/cron.php` может явно указать namespace и каталог команд, после чего
loader построит профильную hash map до запуска event loop:

```php
use Wolfcharaa\Cron\Configuration\Loader\AttributeCronConfigLoader;
use Wolfcharaa\Cron\Discovery\Psr4DirectoryClassProvider;

$scheduler
    ->load(new AttributeCronConfigLoader(
        new Psr4DirectoryClassProvider([
            'App\\Command\\' => dirname(__DIR__) . '/src/Command',
        ]),
    ))
    ->run();
```

Вместо обхода каталога loader-у можно передать `ClassListProvider`,
`Psr4DirectoryClassProvider`, `ComposerClassMapProvider`, `ChainClassProvider`
или `RegexFilterClassProvider`. Несколько источников конфигурации объединяются
через
`CompositeCronConfigLoader`. Карта проверяет конфликты имён внутри каждого
профиля до запуска воркера. Класс команды считается service id и извлекается
из свежего PSR-11 контейнера в child-процессе.

### Классический crontab и exec

Готовый user crontab можно подключить как отдельную конфигурацию профиля:

```php
use Wolfcharaa\Cron\Configuration\Loader\CrontabConfigLoader;

$scheduler->load(CrontabConfigLoader::fromFile(
    path: dirname(__DIR__) . '/config/cron/prod.crontab',
    profile: 'prod',
));
```

Для стабильного имени задачи перед строкой используется комментарий
`# cron:name=cleanup`; без него loader создаёт детерминированное имя из
расписания и команды. Формат `/etc/crontab` с колонкой пользователя включается
через `systemFormat: true`. Сам scheduler не меняет Unix-пользователя: сервис
должен быть заранее запущен под нужным аккаунтом.

Команда выполняется через настроенный `SHELL` внутри отдельного fork-worker.
Поэтому ожидание внешнего процесса не блокирует master event loop, а его exit
code участвует в обычной success-chain семантике.

### YAML-конфигурация

`.yaml` и `.yml` файлы читаются напрямую в PHP через `symfony/yaml`. Внешний
renderer или Lua не нужны:

```php
use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;

$scheduler->load(YamlConfigLoader::fromFile(
    dirname(__DIR__) . '/config/crontab.yaml',
));
```

Каждый профиль хранит свои расписания и runtime-настройки. Поле `then`
задаёт одноразовую цепочку, как и `ChainConfig`:

```yaml
$schema: ../vendor/romanfedorskij/cron/resources/schema/crontab.schema.json
version: 1
profiles:
  stage:
    timezone: Europe/Moscow
    shell: /bin/bash
    working_directory: /var/www/html
    environment:
      APP_ENV: stage
    jobs:
      - name: import
        schedule: "*/15 * * * *"
        command: php bin/console app:import
        overlap_policy: forbid
        then:
          - name: notify
            command: php bin/console app:notify
  prod:
    timezone: UTC
    jobs:
      - name: import
        schedule: "0 * * * *"
        command: php bin/console app:import --prod
```

Схема лежит в `resources/schema/crontab.schema.json`. Loader также строго
проверяет типы, обязательные поля и неизвестные ключи до запуска event
loop.

Для прямой замены mvdcake Lua-renderer поддерживается его формат с корневым
`defaults` и полями `cwd`, `path`, `user`, `log`. Любую из этих настроек можно
переопределить в профиле или отдельной задаче. `user` не переключает Unix-аккаунт:
child-worker проверяет, что scheduler уже запущен под нужным пользователем. `log`
перенаправляет stdout и stderr команды в указанный файл в append-режиме.

Несколько loader-ов можно объединить через `CompositeCronConfigLoader`.

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
