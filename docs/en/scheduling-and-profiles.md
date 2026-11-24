# Scheduling and profiles

Cron uses standard five-field expressions at minute resolution. Pass a string
or `CronExpression` to `cron()`/builder `cron()`, or use the final
`EveryMinutes`, `EveryHours`, `EveryDays`, and `EveryMonths` values in an
attribute or `every()`. There is no custom frequency inheritance; express a
custom cadence with an exact cron expression.

Fluent helpers are `everyMinute(s)`, `hourly`, `everyHours`, `hourlyAt`,
`daily`, `everyDays`, `dailyAt`, `monthly`, `everyMonths`, `at`, `weekdays`,
`weekends`, and `onDaysOfWeek`. Day/month steps are calendar-field steps, not
elapsed durations; DST and month length follow calendar/timezone semantics.

`SchedulerOptions::timezone` applies to every schedule in the worker. There is
no per-task timezone.

A profile is a non-empty name using letters, digits, `.`, `_`, or `-`.
`default` is the default; `any` enables a definition for every active profile
and cannot be combined with a named profile in one declaration. Duplicate task
names across resolved `any` and named profiles are rejected while the immutable
map is constructed.

`withoutOverlapping()` is the default: a new run is skipped while a child with
the same name is active. `allowOverlapping()` permits parallel runs within
global `maxConcurrency`.
