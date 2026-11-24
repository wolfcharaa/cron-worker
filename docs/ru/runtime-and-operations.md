# Runtime и эксплуатация

Master использует внутренний последовательный PCNTL-loop без Revolt и async I/O
framework. Signal handlers только устанавливают flags; изменение schedule,
chain, overlap и concurrency state происходит в основном loop. Между проверками
master ждёт не более 250 мс. Wall clock владеет cron timestamps, а `hrtime()` —
shutdown grace.

Каждая задача выполняется в отдельном child process group. Master полностью
reap-ит `SIGCHLD`, логирует PID/exit/signal и запускает successor только после
успеха. Fork/wait/signal corruption — fatal `CronRuntimeException`; exception
внутри задачи остаётся child outcome: STDERR, exit `255`, parent log.

Первый `SIGINT`/`SIGTERM` прекращает новые runs и отправляет `SIGTERM` всем
активным groups. Второй signal или истечение
`SchedulerOptions::shutdownGracePeriodSeconds` отправляет `SIGKILL`. Задайте
внешний service/container stop timeout больше library grace.

`maxConcurrency` считает root и chain children вместе. Достигнутый лимит
пропускает новый scheduled root, но сохраняет successor в pending queue.
Overlap сравнивается по task name.

Не открывайте master-owned network/database resources до fork. Container factory
вызывается в child на каждый service/Symfony step; так соединения и scoped
services остаются child-local. Logger принадлежит master и получает lifecycle
events; application output принадлежит child.
