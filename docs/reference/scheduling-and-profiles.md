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

В attributes используются те же типы без cron-строки:

```php
use Wolfcharaa\Cron\Attribute\SymfonyTask;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;

#[SymfonyTask(schedule: new EveryMinutes(15))]
```

`CronTask` и `SymfonyTask` принимают `EveryTime`, `CronExpression` или строку.
Строка нужна для точного календарного правила; типизированный интервал удобнее
для обычного «каждые N минут/часов/дней».

## Исторические смещения между задачами

Forked workers не блокируют master, поэтому независимые задачи не требуется
разносить по разным минутам только ради последовательного исполнения. Такие
смещения можно заменить одинаковой естественной границей интервала после
проверки `maxConcurrency` и допустимости параллельного запуска.

Не упрощайте expression, если конкретная минута имеет бизнес-смысл. Зависимые
задачи связывайте success-chain, а не приблизительной задержкой в cron. Учтите:
корневой scheduled tick пропускается при занятом global concurrency limit;
one-shot chain step, напротив, остаётся в очереди.

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
$scheduler->cron('legacy-task', '*/5 * * * *', $handler);
```

Timezone принадлежит всему scheduler runtime и задаётся один раз:

```php
$options = new SchedulerOptions(timezone: 'Europe/Moscow');
```

Так все задачи одного worker-а интерпретируют cron-поля одинаково. Отдельной
timezone у `CronSchedule`, task builder, attribute или YAML job нет.

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

То же правило действует для declarative configuration:

```php
#[CronTask(
    schedule: '*/15 * * * *',
    profiles: ['stage', 'prod'],
    method: 'run',
)]
```

Если schedule или arguments различаются, используйте несколько repeatable
attributes. Если конфигурация одинакова, один attribute с `profiles` не
дублирует definition в исходном коде.

`SchedulerOptions::profile` принимает строку, `Profile` или string-backed enum.
`any` нельзя объединять с именованными profiles одной задачи. Если shared и
именованный профиль содержат одинаковое task name, карта отклоняется как
неоднозначная.

Profile применяется только к корневой scheduled-задаче. После её запуска вся
success-chain выполняется без повторной profile-фильтрации.
