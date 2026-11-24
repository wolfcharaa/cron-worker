# Cron

[Русский](README.md)

`romanfedorskij/cron` is a Unix library for a long-running cron worker. It
evaluates minute-resolution schedules, runs each task in a separate process,
and owns overlap, success-chain, and graceful-shutdown behavior.

Use it for PHP services, Symfony Commands, or shell jobs behind one scheduler
process. It is not a Windows scheduler, sub-minute timer, distributed queue,
Unix-user switcher, or Kubernetes one-shot runner.

Requirements: PHP 8.2–8.5, Unix, and the `pcntl`, `posix`, and `ctype`
extensions.

## Install

```bash
composer require romanfedorskij/cron:^0.2
```

## First observable run

Create a clean project and copy the packaged example:

```bash
mkdir cron-heartbeat && cd cron-heartbeat
composer init --name=app/cron-heartbeat --license=proprietary --no-interaction
composer require romanfedorskij/cron:^0.2
mkdir -p bin
cp vendor/romanfedorskij/cron/examples/consumer/bin/heartbeat.php bin/
php bin/heartbeat.php
```

No later than the next minute boundary, a forked child prints:

```text
cron-heartbeat:heartbeat
```

Press `Ctrl-C`. The master sends `SIGTERM` to active process groups, waits for
the grace period, and exits; groups still alive after the grace receive
`SIGKILL`.

## Continue

- [Getting started](docs/en/getting-started.md)
- [Configuration sources](docs/en/configuration-sources.md)
- [Symfony Console](docs/en/symfony-console-tasks.md)
- [YAML exec catalog](docs/en/yaml-exec-catalog.md)
- [Success chains](docs/en/success-chains.md)
- [Scheduling and profiles](docs/en/scheduling-and-profiles.md)
- [Runtime and operations](docs/en/runtime-and-operations.md)
- [Migrating system cron](docs/en/migrating-system-cron.md)
- [Public API](docs/en/public-api.md)
- [Migration 0.1 → 0.2](docs/en/migration-0.1-to-0.2.md)

License: [MIT](LICENSE).
