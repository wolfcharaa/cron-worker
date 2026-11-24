# Расписания и profiles

Cron использует стандартные пятичастные expressions с минутной точностью.
Передайте строку/`CronExpression` в `cron()` или builder `cron()`, либо final
values `EveryMinutes`, `EveryHours`, `EveryDays`, `EveryMonths` в attribute/
`every()`. Custom frequency inheritance отсутствует: сложный cadence выражайте
точным cron expression.

Fluent helpers: `everyMinute(s)`, `hourly`, `everyHours`, `hourlyAt`, `daily`,
`everyDays`, `dailyAt`, `monthly`, `everyMonths`, `at`, `weekdays`, `weekends`,
`onDaysOfWeek`. Day/month steps — calendar-field steps, а не elapsed durations;
DST и длина месяца следуют calendar/timezone semantics.

`SchedulerOptions::timezone` применяется ко всем schedules worker-а. Отдельного
timezone на task нет.

Profile — непустое имя из букв, цифр, `.`, `_`, `-`. `default` используется по
умолчанию; `any` включает definition для всех active profiles и не может стоять
рядом с named profile в одной declaration. Одинаковое task name в разрешённом
`any` и named profile отклоняется при создании immutable map.

`withoutOverlapping()` — default: новый run пропускается, если одноимённый child
ещё работает. `allowOverlapping()` разрешает параллельные runs в пределах
глобального `maxConcurrency`.
