# Public API migration map

Use these mappings while editing the consumer application. They summarize the
published API so integration does not require reading `vendor/.../src`.

## Existing classic crontab

Keep the file unchanged unless it contains `CRON_TZ`; move that value to the
worker-wide `SchedulerOptions::timezone` before loading it:

```php
$loader = CrontabConfigLoader::fromFile(
    path: $projectRoot . '/config/cron/prod.crontab',
    profile: 'prod',
    systemFormat: false,
);
```

Set `systemFormat: true` only for files with the `/etc/crontab` user column.
To migrate once into YAML from an installed package, use the public converter
from an application-owned migration script:

```php
use Wolfcharaa\Cron\Configuration\Conversion\CrontabYamlConverter;

$contents = file_get_contents($projectRoot . '/config/cron/prod.crontab');

if ($contents === false) {
    throw new RuntimeException('Unable to read the source crontab.');
}

$yaml = (new CrontabYamlConverter())->convert(
    contents: $contents,
    profile: 'prod',
    schema: '../vendor/romanfedorskij/cron/resources/schema/crontab.schema.json',
);

file_put_contents($projectRoot . '/config/crontab.yaml', $yaml);
```

Review generated task names and all environment/user/cwd/log fields before
cutover. The library source checkout also contains the development-only Symfony
Console wrapper `bin/crontab-to-yaml`; do not assume it is exposed as a Composer
binary in a consumer application.

## YAML profiles

```php
$loader = YamlConfigLoader::fromFile($projectRoot . '/config/crontab.yaml');
```

Reference the shipped schema from the application file:

```yaml
$schema: ../vendor/romanfedorskij/cron/resources/schema/crontab.schema.json
version: 1
profiles:
  prod:
    jobs:
      - name: import
        schedule: "*/15 * * * *"
        command: php bin/console app:import
```

YAML jobs and their `then` steps are exec tasks. Use attributes or manual PHP
configuration for service/static/container callbacks.

## Legacy YAML generated through Lua

Do not port the Lua runtime or execute it from cron-worker. Identify both the
authoritative source files and the final rendered catalog. Replace rendering
with `YamlConfigLoader` over a maintained YAML/YML file. The loader understands
the compatible `defaults`, `cwd`, `path`, `user` and `log` fields; preserve
their profile/job precedence while moving dynamic deployment values to explicit
environment or application configuration.

Compare the PHP-loaded profile map with the legacy rendered output before
removing the renderer. Keep the legacy path available for rollback until the
same task names, commands and schedules are observed.

## Existing command or service classes

Use one attribute for profiles with identical settings. Add repeatable
attributes only when schedule, arguments or chain differ:

```php
#[CronTask(
    schedule: '*/15 * * * *',
    profiles: ['stage', 'prod'],
    method: 'run',
)]
final class ImportCommand
{
    public function run(): int
    {
        return 0;
    }
}
```

The application must explicitly define discovery scope:

```php
$loader = new AttributeCronConfigLoader(
    new Psr4DirectoryClassProvider([
        'App\\Command\\' => $projectRoot . '/src/Command',
    ]),
);
```

Use `ClassListProvider` for a small explicit set, `ComposerClassMapProvider` for
an existing optimized class map, `ChainClassProvider` to combine providers and
`RegexFilterClassProvider` to narrow a broad provider.

## Manual registrations

```php
$scheduler->task('cleanup', $callable)->dailyAt(3);
$scheduler->serviceTask('import', ImportCommand::class, 'run')->everyMinutes(15);
$scheduler->staticTask('warm-cache', CacheWarmup::class, 'call', ['catalog'])->hourly();
$scheduler->containerTask('notify', [PipelineCallbacks::class, 'call'], ['ops'])->dailyAt(9);
```

Container callbacks receive `ContainerInterface`, then
`TaskExecutionContext`, then configured positional arguments. For a successor
use `then()`, `thenService()`, `thenStatic()` or `thenContainer()` on the root or
previous chain builder. A successor has no cron expression and runs once only
after exit code `0`.

## Worker composition

```php
$scheduler = new Scheduler(
    options: new SchedulerOptions(
        profile: $activeProfile,
        maxConcurrency: 4,
        timezone: $timezone,
    ),
    containerFactory: $containerFactory,
);

$scheduler
    ->load(new CompositeCronConfigLoader(...$loaders))
    ->run();
```

Skip `CompositeCronConfigLoader` when there is only one loader. Manual tasks may
be registered on the same scheduler before `load()`.

## Required runtime boundary

- PHP 8.2-8.5 on Unix-like systems with `pcntl` and `posix`
- one long-running application-owned worker entrypoint
- process supervision supplied by the application deployment
- container factory executed inside each child process
- no inherited master-process PDO, Redis or other network clients

For deeper contracts use the package documentation under
`vendor/romanfedorskij/cron/docs/`, especially `guides/configuration-sources.md`,
`guides/task-chains.md`, `reference/container-contract.md` and
`reference/runtime.md`.
