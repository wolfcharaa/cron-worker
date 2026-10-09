# Scheduling and profiles

## Fluent schedule API

Для обычных интервалов cron-синтаксис не нужен:

```php
$scheduler->task('heartbeat', $handler)->everyMinute();
$scheduler->task('sync', $handler)->everyMinutes(15);
$scheduler->task('refresh', $handler)->hourlyAt(10);
$scheduler->task('report', $handler)->dailyAt(9, 30)->weekdays();
$scheduler->task('archive', $handler)->everyDays(3)->at(2);
$scheduler->task('billing', $handler)->monthly()->at(4, 15);
```

Интервалы представлены подтипами `EveryTime`:

```php
use Wolfcharaa\Cron\Schedule\Frequency\EveryTime;

$scheduler
    ->task('sync', $handler)
    ->every(EveryTime::minutes(15));
```

Это календарные cron-интервалы. Например, `everyMinutes(15)` даёт
`*/15 * * * *`, а `everyHours(4)` — `0 */4 * * *`.

## CronExpression

Низкоуровневый immutable value object можно создать из строки или собрать по
полям. Пустое выражение соответствует каждой минуте.

```php
use Wolfcharaa\Cron\Schedule\CronExpression;

$parsed = new CronExpression('15 2 * * MON-FRI');

$configured = (new CronExpression())
    ->withMinute(15)
    ->withHour(2)
    ->withDayOfWeek('MON-FRI');

echo $configured->toString();
```

Строка остаётся доступна через сокращённый API:

```php
$scheduler->cron('legacy-task', '*/5 * * * *', $handler, 'Europe/Moscow');
```

## Profiles

Профиль — именованный план задач для одного scheduler runtime. Имена не
ограничены environment-ами: можно использовать `dev`, `stage`, `prod`,
`default` или имя конкретного сервера.

```php
$scheduler->task('shared', $handler); // any — входит во все profiles

$scheduler
    ->task('ftp-upload', $handler)
    ->profiles('default', 'server-10.11.166.100');
```

`SchedulerOptions::profile` принимает строку, `Profile` или string-backed enum.
`any` нельзя объединять с именованными profiles одной задачи. Если shared и
именованный профиль содержат одинаковое task name, карта отклоняется как
неоднозначная.

Profile применяется только к корневой scheduled-задаче. После её запуска вся
success-chain выполняется без повторной profile-фильтрации.
