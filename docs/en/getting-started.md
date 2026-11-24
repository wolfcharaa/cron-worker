# Getting started

## Outcome

You will run a real long-running worker, observe output from a forked child no
later than the next minute, and stop the master cleanly with `Ctrl-C`.

## Steps

```bash
mkdir cron-heartbeat && cd cron-heartbeat
composer init --name=app/cron-heartbeat --license=proprietary --no-interaction
composer require romanfedorskij/cron:^0.2
mkdir -p bin
cp vendor/romanfedorskij/cron/examples/consumer/bin/heartbeat.php bin/
php bin/heartbeat.php
```

The example registers a context-aware callable through `Scheduler::task()` and
`everyMinute()`. Expected marker:

```text
cron-heartbeat:heartbeat
```

If the marker does not appear within 60 seconds, check system time, stdout, and
the `pcntl`/`posix` extensions. Stop the worker with `Ctrl-C`; the normal exit
status is `0`. Do not use `kill -9` as the normal stop path: the master must be
allowed to terminate child process groups.

Next, choose a [configuration source](configuration-sources.md) and read the
[operations contract](runtime-and-operations.md).
