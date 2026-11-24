# Миграция 0.1 → 0.2

`0.2` — намеренное breaking-сужение pre-1.0 API.

## Переезды

- `Runtime\SchedulerOptions` → `SchedulerOptions`; option
  `clockResyncIntervalSeconds` удалён.
- `Task\TaskExecutionContext` → `TaskExecutionContext`.
- `Registry\CronProfileMap`/`CronTaskDefinition` → `Configuration`.
- `OneShotTaskBuilder` → `ChainTaskBuilder`.

## Удалённые extension/runtime paths

- `SchedulerInterface`, `Scheduler::add()`/`tasks()` и injected clock,
  supervisor, validator;
- Revolt runtime, public process/clock classes, `Schedule`, `CronSchedule`,
  `ScheduleBuilder`, direct `Task`/`ScheduledTask`/`OneShotTask`;
- `TaskHandler`, concrete handlers, `ContainerFactory`, standalone validator/
  issue/result и granular exceptions;
- `TaskConfigInterface::createHandler()`, mutable map `add`/`merge`/
  `addProfile`/`profileConfigs`, `CronProfileConfig`;
- `staticTask`, `containerTask`, `thenStatic`, `thenContainer`,
  `StaticTaskConfig`, `ContainerTaskConfig`;
- custom `CronTaskAttribute`, abstract `EveryTime`, chain/regex/Composer-map
  class providers;
- live `CrontabConfigLoader` и public converter class.

Замены: custom handler → callable/invokable object; static work → callable;
container callback → explicit PSR-11 service; custom config → supported concrete
config in custom loader; mutable map → collect definitions and construct a new
`CronProfileMap`; live crontab → one-time installed `crontab-to-yaml` conversion.

## Изменение поведения

- service arguments должны быть positional; callable принимает 0 или один
  `TaskExecutionContext` и возвращает `null|int<0,255>`;
- Symfony command всегда разрешается из fresh child container по class-string;
- YAML требует `version: 1`, разрешает neutral `profiles: {}` и запрещает
  неизвестные keys;
- configuration load атомарен; public failures сведены к
  `InvalidCronConfiguration`/`CronRuntimeException`;
- Revolt заменён внутренним PCNTL-loop с ожиданием ≤250 мс и group shutdown.

Обновите imports/constraint, выполните preflight всех profiles, integration
smoke child container/YAML/CLI и только затем делайте production cutover.
