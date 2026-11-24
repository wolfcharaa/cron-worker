# Публичный API 0.2

Поддерживаемый PHP API ограничен этим allowlist. Всё в `Internal` и public
assembly hooks builders с `@internal` не является consumer contract.

| Роль | Символы |
| --- | --- |
| Facade/values | `Scheduler`, `SchedulerOptions`, `TaskExecutionContext` |
| Attributes | `Attribute\CronTask`, `Attribute\SymfonyTask` |
| Configuration | `Profile`, `ChainConfig`, `SymfonyInput`, `ServiceTaskConfig`, `SymfonyCommandTaskConfig`, `ExecTaskConfig`, `CronProfileMap`, `CronTaskDefinition` |
| Loaders | `CronConfigLoaderInterface`, `AttributeCronConfigLoader`, `CompositeCronConfigLoader`, `YamlConfigLoader` |
| Discovery | `ClassProviderInterface`, `ClassListProvider`, `Psr4DirectoryClassProvider` |
| Scheduling | `CronExpression`, `EveryMinutes`, `EveryHours`, `EveryDays`, `EveryMonths` |
| Fluent task API | `OverlapPolicy`, `ScheduledTaskBuilder`, `ChainTaskBuilder` |
| Failures | `InvalidCronConfiguration`, `CronRuntimeException` |
| Installed CLI | `crontab-to-yaml` |

`Scheduler` поддерживает constructor `(options, logger, containerFactory)` и
методы `task`, `serviceTask`, `cron`, `load`, `run`. Builders поддерживают
schedule/profile/overlap/`then`/`thenService` fluent calls; их constructors,
`assemble()` и `internalTask()` принадлежат package.

`CronProfileMap` immutable: `definitions()`, `profiles()`, `forProfile()`.
Config values содержат данные и introspection (`name`, `overlapPolicy`), но не
создают handlers. Public exceptions разделяют preflight/configuration failure и
fatal master runtime failure; child exception пересекает process boundary только
как exit/log.
