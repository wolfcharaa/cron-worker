# Runtime and operations

The master uses an internal sequential PCNTL loop without Revolt or an async I/O
framework. Signal handlers only set flags; schedule, chain, overlap, and
concurrency state changes occur in the main loop. The master waits no longer
than 250 ms between checks. Wall clock owns cron timestamps while `hrtime()`
owns shutdown grace.

Every task runs in a separate child process group. The master fully reaps
`SIGCHLD`, logs PID/exit/signal, and starts a successor only after success.
Fork/wait/signal state corruption is a fatal `CronRuntimeException`; an
exception inside a task remains a child outcome: STDERR, exit `255`, and a
parent log event.

The first `SIGINT`/`SIGTERM` stops new runs and sends `SIGTERM` to all active
groups. A second signal or expiry of
`SchedulerOptions::shutdownGracePeriodSeconds` sends `SIGKILL`. Configure the
outer service/container stop timeout above the library grace.

`maxConcurrency` counts root and chain children together. A reached limit skips
a newly scheduled root but keeps a successor in the pending queue. Overlap is
matched by task name.

Do not open master-owned network or database resources before fork. The
container factory runs inside the child for each service/Symfony step, keeping
connections and scoped services child-local. The logger belongs to the master;
application output belongs to the child.
