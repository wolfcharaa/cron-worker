# Application worker

Готовая PHP-заготовка находится в `examples/application-worker.php`. В
приложении её можно скопировать в `bin/cron.php` и заменить integration points.

## Что настраивается

- `APP_CRON_PROFILE` выбирает активный план;
- `Psr4DirectoryClassProvider` получает namespace и каталог команд;
- `config/container.php` возвращает свежий PSR-11 container;
- YAML и classic crontab подключаются только если файлы существуют;
- manual tasks регистрируются перед `load()`;
- `SchedulerOptions` задаёт concurrency и shutdown limits.

## Порядок bootstrap

```text
autoload
  -> profile/options
  -> child container factory
  -> explicit class provider
  -> YAML/crontab loaders
  -> CompositeCronConfigLoader
  -> preflight validation
  -> Revolt event loop
```

Container bootstrap хранится как callback и не выполняется при сборке карты.
Он будет вызван внутри service/container handler после fork.

## Минимальная композиция

```php
$loaders = [
    new AttributeCronConfigLoader(
        new Psr4DirectoryClassProvider([
            'App\\Command\\' => $projectRoot . '/src/Command',
        ]),
    ),
];

if (is_file($yamlConfig)) {
    $loaders[] = YamlConfigLoader::fromFile($yamlConfig);
}

$scheduler = new Scheduler(
    options: new SchedulerOptions(profile: $activeProfile),
    containerFactory: $containerFactory,
);

$scheduler
    ->load(new CompositeCronConfigLoader(...$loaders))
    ->run();
```

Полный копируемый код: [`../../examples/application-worker.php`](../../examples/application-worker.php).
