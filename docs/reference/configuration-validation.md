# Configuration validation

`CronConfigValidator` проверяет итоговую `CronProfileMap` до регистрации задач
и запуска Revolt. `Scheduler::load()` автоматически валидирует активный profile.

## Что проверяется

- для static task: существование класса и method, `public static`, аргументы и
  допустимый declared return type;
- для container callback: два первых аргумента под container/context,
  дополнительные arguments и return type;
- наличие `ContainerFactory` для service/container задач;
- создание handler-а для каждого корневого и chain task config;
- точный path каждой ошибки внутри profile map.

Допустимый declared return type — `int`, `null`, `void`, `never`, `mixed` либо
совместимая union-комбинация. Фактический integer всё равно проверяется во время
выполнения и должен лежать в диапазоне `0..255`.

## Проверка отдельно от Scheduler

```php
use Wolfcharaa\Cron\Validation\CronConfigValidator;

$map = $loader->load();
$result = (new CronConfigValidator())->validate(
    map: $map,
    containerFactory: $containerFactory,
    profile: 'prod',
);

foreach ($result->issues as $issue) {
    printf("%s: %s\n", $issue->path, $issue->message);
}

$result->assertValid();
```

Если `profile` не передан, валидируются определения всех profiles. Это удобно
для отдельной CI/bootstrap проверки. `Scheduler::load()` проверяет только
активный profile и wildcard `any`, который реально попадёт в runtime.

## Atomic load

Validator выполняется до первого вызова `Scheduler::task()`. Если в позднем
шаге chain есть ошибка, scheduler не остаётся частично заполненным задачами из
начала карты.

## Что намеренно не проверяется в master

Validator не создаёт PSR-11 container и не открывает application connections.
Для opaque `serviceId` нельзя заранее доказать, что `container->get()` вернёт
callable нужного типа, не нарушив child-local scope. Такая ошибка остаётся
runtime-ошибкой конкретного child process.

YAML syntax/schema и crontab grammar проверяются соответствующими loaders ещё
до построения `CronProfileMap`.
