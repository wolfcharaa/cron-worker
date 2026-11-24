# Cron 0.2 public migration map

Use the installed package documentation as the source of API truth. This page
maps integration choices to those contracts.

## Worker composition and preflight

```php
$scheduler = new Scheduler(
    options: new SchedulerOptions(
        profile: $activeProfile,
        timezone: $timezone,
        maxConcurrency: 8,
        shutdownGracePeriodSeconds: 20,
    ),
    logger: $logger,
    containerFactory: static fn(): ContainerInterface => buildFreshContainer(),
);

$scheduler->load($loader); // preflight stops here
$scheduler->run();
```

The factory executes in each child step. Never capture a master-built container
or live network resources.

## Attributes and services

Use final `CronTask` for a PSR-11 service and `SymfonyTask` for a Symfony
`Command`. The class-string is the service id. A Symfony command must be
registered by class-string; there is no reflection fallback. `SymfonyInput`
uses named arguments/options, with option names written without `--`.

Discover only explicit classes/roots through `ClassListProvider` or
`Psr4DirectoryClassProvider`. Custom attributes are unsupported; map custom
declarations in a custom loader.

## Manual work and chains

Use `task()`/`then()` for a zero-argument or `TaskExecutionContext` callable.
Use `serviceTask()`/`thenService()` for a child-local PSR-11 service with
positional arguments. Static work is an explicit callable; do not recreate the
removed static/container-callback APIs. Successors run only after exit `0`.

## YAML exec

YAML requires `version: 1`; empty `profiles: {}` is valid. It contains shell
root jobs and shell `then` steps only. Preserve shell, cwd/path, environment,
effective-user check, output log, profile, overlap, and command quoting exactly.
Validate against `resources/schema/crontab.schema.json` and load with
`YamlConfigLoader`.

## Classic crontab conversion

```bash
vendor/bin/crontab-to-yaml source.crontab --output=cron.yaml --profile=prod
vendor/bin/crontab-to-yaml /etc/crontab --system --output=cron.yaml --profile=prod
```

Conversion fails closed for `@reboot`, `CRON_TZ`, `%` newline semantics,
unknown shortcuts, and malformed lines. Review the generated catalog; conversion
does not authorize disabling the old scheduler. Keep the source for rollback.

## Capacity and cutover

`maxConcurrency` counts roots and successors. A full limit skips a scheduled
root but queues a successor. Keep `OverlapPolicy::Forbid` unless parallel runs
are safe. Simplify legacy minute staggering only after proving no business-time
contract, dependency, shared-state conflict, or peak-capacity problem.

Use the paired package guides under `docs/en` or `docs/ru` for configuration,
Symfony, YAML, chains, runtime, crontab migration, API, and `0.1 → 0.2` changes.
