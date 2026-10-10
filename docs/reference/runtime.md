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

`OverlapPolicy` отвечает только на один вопрос: что делать с очередным cron
tick, если предыдущий child-process задачи с тем же name ещё работает.

| Policy | Поведение очередного scheduled tick |
| --- | --- |
| `Forbid` | Tick пропускается, пока процесс с тем же task name активен |
| `Allow` | Scheduler запускает ещё один child, если есть global concurrency slot |

Например, задача запускается каждую минуту, но работает четыре минуты. С
`Forbid` в один момент существует не больше одного её процесса; ticks между
стартом и завершением не копятся. С `Allow` каждую минуту может появляться новый
процесс, пока это допускает `maxConcurrency`.

Policy не ставит lock и не блокирует event loop: master делает
`isTaskRunning()`, записывает skip в лог и продолжает обслуживать остальные
таймеры. Защита нужна для неидемпотентных import/export, cleanup и sync задач,
которые нельзя безопасно выполнять параллельно. Для независимых запусков можно
явно вызвать `allowOverlapping()`. Классический crontab loader выбирает `Allow`,
чтобы сохранить обычную cron-семантику.

У success-chain поведение чуть отличается: one-shot шаг не теряется, а остаётся
во внутренней очереди до освобождения процесса с тем же task name.

## maxConcurrency

`SchedulerOptions::maxConcurrency` ограничивает суммарное число child workers.

Если limit занят:

- scheduled task пропускает текущий cron tick;
- one-shot chain task остаётся в очереди до освобождения slot.

Это независимый предел: `OverlapPolicy` ограничивает повторы одной task name,
а `maxConcurrency` — все одновременно работающие child-процессы runtime.

## Timezone

`SchedulerOptions::timezone` применяется ко всем cron-выражениям одного
scheduler runtime. По умолчанию используется `UTC`. Timezone не хранится в
отдельных задачах, attributes или YAML profiles, потому что worker обслуживает
один серверный clock contract.

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
