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

Один `CronTask` может включать одинаковую конфигурацию сразу в несколько
профилей. Если расписание или arguments отличаются, добавьте второй repeatable
attribute.

```php
use Wolfcharaa\Cron\Attribute\CronTask;

#[CronTask(
    schedule: '*/10 * * * *',
    profiles: ['stage', 'prod'],
    method: 'run',
)]
#[CronTask(schedule: '0 * * * *', profiles: 'nightly', method: 'run')]
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

Для Symfony Console command используйте `SymfonyTask`: библиотека сама
соберёт `ArrayInput`, получит command, `HelperSet` и `OutputInterface` из
child-local container и вызовет стандартный `Command::run()`.

```php
use Symfony\Component\Console\Command\Command;
use Wolfcharaa\Cron\Attribute\SymfonyTask;

#[SymfonyTask(
    schedule: '*/5 * * * *',
    profiles: ['stage', 'prod'],
    name: 'refresh-search-index',
    arguments: ['people'],
)]
final class RefreshSearchIndexCommand extends Command
{
    // configure() and execute()
}
```

Приложение может связать `OutputInterface` со своим файловым output и
зарегистрировать `HelperSet` для framework-specific helpers. Отдельный static
callback рядом с command не нужен.

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
    jobs:
      - name: import
        schedule: "0 * * * *"
        command: php bin/console app:import --prod
```

Схема находится в `resources/schema/crontab.schema.json`. YAML-loader проверяет
обязательные поля, типы и неизвестные ключи. Формат также понимает совместимые
поля `defaults`, `cwd`, `path`, `user` и `log` из cron-каталога mvdcake.

Timezone не является частью YAML job/profile: задайте её один раз через
`SchedulerOptions(timezone: 'Europe/Moscow')` в worker bootstrap.

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
- `CRON_TZ` отклоняется: перенесите его значение в `SchedulerOptions::timezone`;
- scheduler не меняет Unix user, а проверяет уже активное имя пользователя
  или числовой effective UID;
- задачи crontab по умолчанию разрешают overlap, как классический cron.

Перенос существующего списка в YAML:

```shell
crontab -l | bin/crontab-to-yaml \
  --profile=prod \
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
