# Runtime

## Master и child workers

Master-процесс не выполняет task handlers. Он:

- рассчитывает ближайшее время запуска;
- держит таймер Revolt;
- обрабатывает `SIGCHLD`, `SIGTERM` и `SIGINT`;
- запускает задачи через `pcntl_fork()`;
- неблокирующе собирает завершившиеся процессы через `waitpid(..., WNOHANG)`;
- ставит следующий success-chain шаг во внутреннюю очередь.

Блокирующий PHP-код или ожидание внешней команды происходят в child-process и
не останавливают event loop master-а.

## OverlapPolicy

`OverlapPolicy` действует на одну task name.

- `Forbid` используется по умолчанию и пропускает новый tick, пока предыдущий
  процесс той же задачи работает.
- `Allow` разрешает несколько процессов одной задачи одновременно и включается
  через `allowOverlapping()` либо конфигурацию loader-а.

Это защита от повторной неидемпотентной работы, а не механизм асинхронности.

## maxConcurrency

`SchedulerOptions::maxConcurrency` ограничивает суммарное число child workers.

Если limit занят:

- scheduled task пропускает текущий cron tick;
- one-shot chain task остаётся в очереди до освобождения slot.

## Clock resync

`clockResyncIntervalSeconds` ограничивает длину одного таймера. Scheduler
периодически сверяется с wall clock, поэтому изменение системного времени не
оставляет один далёкий таймер навсегда рассчитанным по старому clock.

## Graceful shutdown

- первый `SIGTERM` или `SIGINT` останавливает планирование, очищает pending
  chain и отправляет `SIGTERM` активным children;
- после `shutdownGracePeriodSeconds` оставшиеся процессы получают `SIGKILL`;
- повторный shutdown signal сразу отправляет `SIGKILL`;
- `SIGCHLD` освобождает завершившиеся процессы без блокировки.

## Fork boundary

После `fork` child наследует память и открытые file descriptors master-а. Не
используйте одновременно в parent и child заранее открытые PDO, Redis или иные
сетевые соединения. Создавайте process-local ресурсы через container factory
или непосредственно внутри handler-а.

`pcntl_fork()` не даёт полной изоляции `fork + exec`; библиотека пока не
закрывает этот отдельный supervisor-сценарий.
