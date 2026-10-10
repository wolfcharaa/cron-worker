# Quick start

Этот пример собирает scheduler вручную, регистрирует одну задачу и запускает
Revolt runtime.

## 1. Создайте scheduler

```php
use Wolfcharaa\Cron\Runtime\SchedulerOptions;
use Wolfcharaa\Cron\Scheduler;

$scheduler = new Scheduler(
    new SchedulerOptions(
        profile: 'default',
        maxConcurrency: 4,
        timezone: 'Europe/Moscow',
    ),
);
```

`profile` выбирает один план задач. `timezone` задаёт часовую зону для всех
cron-выражений этого runtime. `maxConcurrency` ограничивает суммарное число
работающих child-процессов.

## 2. Зарегистрируйте задачу

```php
$scheduler
    ->task('cleanup', static function (): int {
        removeExpiredFiles();

        return 0;
    })
    ->dailyAt(3, 15)
    ->withoutOverlapping();
```

Handler возвращает `null` либо exit code `0..255`. Код `0` означает успех.
Исключение, сигнал или ненулевой код останавливает success-chain.

## 3. Запустите event loop

```php
$scheduler->run();
```

До запуска event loop scheduler строит задачи и проверяет manual callable.
Конфигурация, переданная через `load()`, дополнительно проходит атомарную
preflight validation ещё до регистрации первой задачи. После старта master
только ждёт таймеры и сигналы; task handler выполняется после `pcntl_fork()`.

## Читаемые интервалы

```php
$scheduler->task('heartbeat', $handler)->everyMinute();
$scheduler->task('sync', $handler)->everyMinutes(15);
$scheduler->task('refresh', $handler)->hourlyAt(10);
$scheduler->task('report', $handler)->dailyAt(9, 30)->weekdays();
$scheduler->task('archive', $handler)->everyDays(3)->at(2);
$scheduler->task('billing', $handler)->monthly()->at(4, 15);
```

Следующие шаги:

- [Configuration sources](configuration-sources.md)
- [Task chains](task-chains.md)
- [Application worker](../examples/application-worker.md)
