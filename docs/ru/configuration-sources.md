# Источники конфигурации

Выберите один или скомпонуйте несколько источников. `Scheduler::load()` сначала
полностью компилирует выбранный profile и только затем атомарно добавляет задачи.
Ошибки конфигурации имеют тип `InvalidCronConfiguration` и содержат source/path.

## Manual API

`task()` принимает `callable(): int|null` или
`callable(TaskExecutionContext): int|null`. `serviceTask()` получает service из
нового child-local PSR-11 container; arguments — только positional list.

```php
$scheduler = new Scheduler(
    containerFactory: static fn(): ContainerInterface => buildContainer(),
);
$scheduler->serviceTask('cleanup', Cleanup::class, arguments: ['expired'])
    ->dailyAt(2, 15)
    ->profile('prod');
```

## Attributes

`AttributeCronConfigLoader` читает только built-in `CronTask` и `SymfonyTask`.
Передайте `ClassListProvider` или явные PSR-4 roots через
`Psr4DirectoryClassProvider`. Plain service пример: [attribute-service.php](../../examples/attribute-service.php).
Class-string одновременно является PSR-11 service id.

## YAML exec

`YamlConfigLoader` загружает только versioned shell catalog; PHP services в YAML
не кодируются. Пример: [cron.yaml](../../examples/cron.yaml).

## Custom loader и композиция

Реализуйте `CronConfigLoaderInterface` и верните законченный immutable
`CronProfileMap` из поддерживаемых `CronTaskDefinition`. Собственный config или
handler SPI отсутствует. `CompositeCronConfigLoader` объединяет definitions,
не изменяя карты дочерних loaders.

Preflight создаёт `Scheduler` с production options/factory и вызывает `load()`,
но не `run()`: [preflight.php](../../examples/preflight.php).
