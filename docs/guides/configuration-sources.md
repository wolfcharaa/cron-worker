# Configuration sources

Scheduler поддерживает четыре независимых источника. Их можно использовать по
одному или объединять через `CompositeCronConfigLoader`.

| Источник | Когда использовать |
| --- | --- |
| Manual API | Небольшая конфигурация прямо в `bin/cron.php` |
| Attributes | Задача и её profiles находятся рядом с классом команды |
| YAML | Ops-friendly каталог exec-команд с отдельными профилями |
| Crontab | Подключение существующего стандартного crontab без новых директив |

## Manual API

```php
$scheduler
    ->task('cleanup', $handler)
    ->dailyAt(3)
    ->profile('prod');
```

Manual-задачи можно добавить до или после `load()`, пока не вызван `run()`.

## Attributes

`CronTask` repeatable: одна команда может иметь полностью независимые
расписания в разных профилях.

```php
use Wolfcharaa\Cron\Attribute\CronTask;

#[CronTask(schedule: '*/10 * * * *', profile: 'stage', method: 'run')]
#[CronTask(schedule: '0 * * * *', profile: 'prod', method: 'run')]
final class ImportCommand
{
    public function run(): int
    {
        return 0;
    }
}
```

Какие классы обходить, явно решает приложение:

```php
use Wolfcharaa\Cron\Configuration\Loader\AttributeCronConfigLoader;
use Wolfcharaa\Cron\Discovery\Psr4DirectoryClassProvider;

$loader = new AttributeCronConfigLoader(
    new Psr4DirectoryClassProvider([
        'App\\Command\\' => dirname(__DIR__, 2) . '/src/Command',
    ]),
);
```

Доступны `ClassListProvider`, `Psr4DirectoryClassProvider`,
`ComposerClassMapProvider`, `ChainClassProvider` и `RegexFilterClassProvider`.
Никакого скрытого project scan библиотека не выполняет.

## YAML

```php
use Wolfcharaa\Cron\Configuration\Loader\YamlConfigLoader;

$loader = YamlConfigLoader::fromFile(
    dirname(__DIR__, 2) . '/config/crontab.yaml',
);
```

```yaml
$schema: ../vendor/romanfedorskij/cron/resources/schema/crontab.schema.json
version: 1
profiles:
  stage:
    timezone: Europe/Moscow
    shell: /bin/bash
    environment:
      APP_ENV: stage
    jobs:
      - name: import
        schedule: "*/15 * * * *"
        command: php bin/console app:import
        then:
          - name: notify
            command: php bin/console app:notify
  prod:
    timezone: UTC
    jobs:
      - name: import
        schedule: "0 * * * *"
        command: php bin/console app:import --prod
```

Схема находится в `resources/schema/crontab.schema.json`. YAML-loader проверяет
обязательные поля, типы и неизвестные ключи. Формат также понимает совместимые
поля `defaults`, `cwd`, `path`, `user` и `log` из cron-каталога mvdcake.

YAML `then` описывает exec-шаги. PHP service/static/container шаги задаются
manual API или `ChainConfig` внутри attributes.

## Классический crontab

```php
use Wolfcharaa\Cron\Configuration\Loader\CrontabConfigLoader;

$loader = CrontabConfigLoader::fromFile(
    path: dirname(__DIR__, 2) . '/config/cron/prod.crontab',
    profile: 'prod',
);
```

Поддерживаются комментарии, environment variables, пятипольные выражения и
calendar macros вроде `@daily`. Для `/etc/crontab` с колонкой пользователя
передайте `systemFormat: true`.

Ограничения совместимости с daemon cron:

- `@reboot`, cron-mail и специальная обработка `%` не эмулируются;
- scheduler не меняет Unix user, а проверяет уже активного пользователя;
- задачи crontab по умолчанию разрешают overlap, как классический cron.

Перенос существующего списка в YAML:

```shell
crontab -l | bin/crontab-to-yaml \
  --profile=prod \
  --timezone=Europe/Moscow \
  --schema=../vendor/romanfedorskij/cron/resources/schema/crontab.schema.json \
  --output=config/crontab.yaml
```

## Composite loader

```php
use Wolfcharaa\Cron\Configuration\Loader\CompositeCronConfigLoader;

$scheduler->load(new CompositeCronConfigLoader(
    $attributeLoader,
    $yamlLoader,
    $crontabLoader,
));
```

Все источники сводятся в `CronProfileMap`. Конфликт имён внутри одного профиля
является ошибкой до запуска worker-а.
