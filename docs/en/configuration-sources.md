# Configuration sources

Choose one source or compose several. `Scheduler::load()` compiles the selected
profile completely and only then adds its tasks atomically. Configuration
failures use `InvalidCronConfiguration` and include source/path context.

## Manual API

`task()` accepts `callable(): int|null` or
`callable(TaskExecutionContext): int|null`. `serviceTask()` resolves its service
from a fresh child-local PSR-11 container; arguments must be a positional list.

```php
$scheduler = new Scheduler(
    containerFactory: static fn(): ContainerInterface => buildContainer(),
);
$scheduler->serviceTask('cleanup', Cleanup::class, arguments: ['expired'])
    ->dailyAt(2, 15)
    ->profile('prod');
```

## Attributes

`AttributeCronConfigLoader` reads only the built-in `CronTask` and
`SymfonyTask`. Pass a `ClassListProvider` or explicit PSR-4 roots through
`Psr4DirectoryClassProvider`. Plain-service example:
[attribute-service.php](../../examples/attribute-service.php). The class-string
is also its PSR-11 service id.

## YAML exec

`YamlConfigLoader` loads only a versioned shell catalog; YAML does not encode
PHP services. See [cron.yaml](../../examples/cron.yaml).

## Custom loader and composition

Implement `CronConfigLoaderInterface` and return a complete immutable
`CronProfileMap` built from supported `CronTaskDefinition` values. There is no
custom config or handler SPI. `CompositeCronConfigLoader` combines definitions
without mutating child-loader maps.

For preflight, construct `Scheduler` with production options/factory and call
`load()` without `run()`: [preflight.php](../../examples/preflight.php).
