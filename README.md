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
use Wolfcharaa\Cron\Scheduler;

require __DIR__ . '/vendor/autoload.php';

$scheduler = new Scheduler(
    new SchedulerOptions(maxConcurrency: 4),
);

$scheduler->cron(
    name: 'cleanup',
    expression: '*/5 * * * *',
    handler: static function (): int {
        // Блокирующая работа безопасна для master event loop: этот код
        // выполняется в дочернем процессе.
        return 0;
    },
    timezone: 'Europe/Moscow',
);

$scheduler->run();
```

Callable должен вернуть `null` или exit code от `0` до `255`. По умолчанию
повторный запуск задачи не начинается, пока работает её предыдущий процесс.
Глобальная параллельность ограничивается через `SchedulerOptions`.

> После fork дочерний процесс наследует память master-процесса. Не используйте
> одновременно в parent и child заранее открытые PDO, Redis и другие сетевые
> соединения. Создавайте process-local ресурсы внутри callable. Вариант
> `fork + exec` будет добавлен отдельным supervisor-адаптером.

## Сигналы

- `SIGCHLD` неблокирующе освобождает завершившиеся процессы через `WNOHANG`.
- `SIGTERM` и `SIGINT` останавливают планирование и начинают graceful shutdown.
- Повторный сигнал завершения или истечение grace period приводит к `SIGKILL`.

## Разработка

```shell
make check
```
