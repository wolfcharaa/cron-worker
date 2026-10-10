# Symfony Console tasks

`SymfonyTask` запускает обычный Symfony `Command` через публичный
`Command::run()`. Команде не нужен cron-specific callback: она продолжает
получать данные через `InputInterface`, а scheduler создаёт `ArrayInput` внутри
child-процесса.

## Attribute над командой

```php
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wolfcharaa\Cron\Attribute\SymfonyTask;
use Wolfcharaa\Cron\Configuration\SymfonyInput;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;

#[SymfonyTask(
    schedule: new EveryMinutes(15),
    profiles: ['stage', 'prod'],
    name: 'refresh-search-index',
    input: new SymfonyInput(
        arguments: ['index' => 'people'],
        options: ['limit' => 500, 'force' => true],
    ),
)]
#[AsCommand(name: 'app:refresh-search-index')]
final class RefreshSearchIndexCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('index', InputArgument::REQUIRED)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED)
            ->addOption('force', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $index = $input->getArgument('index');
        $limit = $input->getOption('limit');
        $force = $input->getOption('force');

        // application work

        return self::SUCCESS;
    }
}
```

Этот input соответствует обычному запуску:

```shell
php bin/console app:refresh-search-index people --limit=500 --force
```

Имя самой команды в `SymfonyInput` не указывается: scheduler уже знает класс,
который нужно запустить.

## Arguments и options

`SymfonyInput` разделяет два пространства имён Symfony Console:

| CLI | Конфигурация |
| --- | --- |
| `people` для argument `index` | `arguments: ['index' => 'people']` |
| `--limit=500` | `options: ['limit' => 500]` |
| `--force` | `options: ['force' => true]` |
| repeated/array value | `options: ['id' => [10, 20]]` |

Всегда используйте имена из `configure()`. Options задаются без начальных
дефисов: `force`, а не `--force`. Позиционные списки вроде
`arguments: ['people']` намеренно отклоняются — именованный input не зависит от
порядка объявления arguments в команде.

Scheduler создаёт `ArrayInput` с definition конкретной команды и переводит его
в non-interactive режим. Неизвестное имя, отсутствующий обязательный argument
или несовместимое значение приводит к стандартной ошибке Symfony Console до
вызова `execute()`.

## Команда из контейнера

На каждом запуске handler вызывает `ContainerFactory` уже внутри forked child.
Если container содержит service с id, равным class-string команды, используется
этот экземпляр. Так работают constructor dependencies:

```php
static fn(): ContainerInterface => buildFreshContainer();
```

Если service отсутствует, библиотека может создать только concrete command без
обязательных constructor arguments. Иначе зарегистрируйте класс команды в
контейнере.

Опционально container может предоставить:

- `Symfony\Component\Console\Helper\HelperSet` для framework helpers;
- `Symfony\Component\Console\Output\OutputInterface` для управляемого output.

Без `OutputInterface` используется `ConsoleOutput`.

## Symfony command в success-chain

`SymfonyCommandTaskConfig` использует тот же `SymfonyInput`:

```php
use Symfony\Component\Console\Command\Command;
use Wolfcharaa\Cron\Attribute\SymfonyTask;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\SymfonyCommandTaskConfig;
use Wolfcharaa\Cron\Configuration\SymfonyInput;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;

#[SymfonyTask(
    schedule: new EveryMinutes(15),
    chain: new ChainConfig(
        new SymfonyCommandTaskConfig(
            commandId: ReindexCommand::class,
            input: new SymfonyInput(
                arguments: ['index' => 'people'],
                options: ['force' => true],
            ),
            taskName: 'reindex-people',
        ),
    ),
)]
final class ImportPeopleCommand extends Command
{
    // configure() and execute()
}
```

Следующая команда ставится во внутреннюю очередь только после exit code `0` и
не ждёт собственного cron schedule.

## Интервал или точное календарное время

Для обычной периодичности используйте читаемый объект:

```php
schedule: new EveryMinutes(15)
schedule: new EveryHours(2)
schedule: new EveryDays(1)
```

Строковое cron expression оставляйте для календарного требования:

```php
schedule: '15 3 * * MON-FRI'
```

Несколько независимых задач могут иметь одинаковую границу интервала: master
не блокируется их выполнением, а работа идёт в отдельных child-процессах. Не
переносите искусственные смещения вроде `1,16,31,46` и `4,19,34,49` только ради
последовательного запуска старого cron. Перед объединением расписаний проверьте:

- `maxConcurrency` покрывает ожидаемый одновременный старт;
- задачи действительно независимы;
- `OverlapPolicy` выбран для повторного запуска той же задачи;
- реальная зависимость оформлена через `ChainConfig`, а не минутное смещение.

Scheduled tick пропускается, если global concurrency limit уже занят, поэтому
нельзя без проверки capacity сводить неограниченное число jobs на одну минуту.

## Переход с `0.1.0-rc.2`

В `rc.2` Symfony input назывался `arguments` и допускал позиционный список:

```php
arguments: ['people']
```

Замените его именованным bag по definition команды:

```php
input: new SymfonyInput(
    arguments: ['index' => 'people'],
)
```

Для `SymfonyCommandTaskConfig` применяется та же замена. Обычные
`CronTask`, `ServiceTaskConfig`, `StaticTaskConfig` и `ContainerTaskConfig`
по-прежнему используют PHP-аргументы вызываемого метода и не переводятся на
`SymfonyInput`.

## Если команда не запускается

1. Проверьте, что ключи `arguments` и `options` совпадают с именами из
   `configure()`; option пишется без `--`.
2. Для команды с constructor dependencies зарегистрируйте её class-string в
   child-local PSR-11 container.
3. Не создавайте container, PDO или Redis connection в master до fork.
4. Убедитесь, что attribute discovery охватывает namespace и каталог команды.
5. Проверьте активный profile и уникальность task names.
6. Для неизвестного option/argument читайте исходное исключение Symfony
   Console в логе завершившегося child-процесса.
7. Если запуск пропущен, проверьте сообщения о `maxConcurrency` и
   `OverlapPolicy` в lifecycle logger.

Подробнее о fork и пропущенных ticks: [Runtime](../reference/runtime.md).
