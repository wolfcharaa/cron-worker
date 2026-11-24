# Migration 0.1 → 0.2

`0.2` deliberately narrows the pre-1.0 API and is breaking.

## Moves

- `Runtime\SchedulerOptions` → `SchedulerOptions`; the
  `clockResyncIntervalSeconds` option is removed.
- `Task\TaskExecutionContext` → `TaskExecutionContext`.
- `Registry\CronProfileMap`/`CronTaskDefinition` → `Configuration`.
- `OneShotTaskBuilder` → `ChainTaskBuilder`.

## Removed extension/runtime paths

- `SchedulerInterface`, `Scheduler::add()`/`tasks()`, and injected clock,
  supervisor, or validator;
- Revolt runtime, public process/clock classes, `Schedule`, `CronSchedule`,
  `ScheduleBuilder`, and direct `Task`/`ScheduledTask`/`OneShotTask`;
- `TaskHandler`, concrete handlers, `ContainerFactory`, standalone validator/
  issue/result, and granular exceptions;
- `TaskConfigInterface::createHandler()`, mutable map `add`/`merge`/
  `addProfile`/`profileConfigs`, and `CronProfileConfig`;
- `staticTask`, `containerTask`, `thenStatic`, `thenContainer`,
  `StaticTaskConfig`, and `ContainerTaskConfig`;
- custom `CronTaskAttribute`, abstract `EveryTime`, and chain/regex/Composer-map
  class providers;
- live `CrontabConfigLoader` and the public converter class.

Replacements: custom handler → callable/invokable object; static work →
callable; container callback → explicit PSR-11 service; custom config → a
supported concrete config in a custom loader; mutable map → collect definitions
and construct a new `CronProfileMap`; live crontab → one-time conversion with
the installed `crontab-to-yaml` command.

## Behavior changes

- service arguments must be positional; a callable accepts zero arguments or
  one `TaskExecutionContext` and returns `null|int<0,255>`;
- a Symfony command always resolves by class-string from a fresh child
  container;
- YAML requires `version: 1`, accepts neutral `profiles: {}`, and rejects
  unknown keys;
- configuration loading is atomic; public failures collapse to
  `InvalidCronConfiguration`/`CronRuntimeException`;
- Revolt is replaced by an internal PCNTL loop with waits ≤250 ms and process-
  group shutdown.

Update imports and constraints, preflight every profile, run integration smoke
checks for the child container/YAML/CLI, and only then perform production
cutover.
