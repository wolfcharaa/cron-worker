# Public API 0.2

The supported PHP API is limited to this allowlist. Everything under `Internal`
and builder assembly hooks marked `@internal` is outside the consumer contract.

| Role | Symbols |
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

`Scheduler` supports constructor `(options, logger, containerFactory)` and the
`task`, `serviceTask`, `cron`, `load`, and `run` methods. Builders support
schedule/profile/overlap/`then`/`thenService` fluent calls; their constructors,
`assemble()`, and `internalTask()` belong to the package.

`CronProfileMap` is immutable and exposes `definitions()`, `profiles()`, and
`forProfile()`. Config values expose data and introspection (`name`,
`overlapPolicy`) but never construct handlers. Public exceptions separate
preflight/configuration failure from fatal master runtime failure; a child
exception crosses the process boundary only as exit/log data.
