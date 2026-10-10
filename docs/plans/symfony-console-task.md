# Native Symfony Console tasks

## Problem

Applications currently have to add a static callback beside every Symfony
Console command. The callback recreates the same integration work: obtains the
child-process container, builds `ArrayInput`, selects an `OutputInterface`,
attaches a `HelperSet` and calls the command. Besides duplication, this makes
the application responsible for library execution mechanics and encourages
calling protected `execute()` methods directly.

## Target API

```php
use Symfony\Component\Console\Command\Command;
use Wolfcharaa\Cron\Attribute\SymfonyTask;
use Wolfcharaa\Cron\Configuration\SymfonyInput;
use Wolfcharaa\Cron\Schedule\Frequency\EveryMinutes;

#[SymfonyTask(
    schedule: new EveryMinutes(5),
    profiles: ['stage', 'prod'],
    name: 'refresh-search-index',
    input: new SymfonyInput(
        arguments: ['index' => 'people'],
        options: ['force' => true],
    ),
)]
final class RefreshSearchIndexCommand extends Command
{
    // Normal configure() and execute(); no cron-specific wrapper.
}
```

`SymfonyTask` is valid only on a Symfony `Command`. Its default target is the
annotated command class, so the attribute has no callback `method`. Profiles
with identical schedule, input and chain share one attribute; use a
repeatable attribute only when those settings differ.

Timezone is deliberately absent from the task. One
`SchedulerOptions::timezone` applies to every cron expression in the worker.

## Runtime contract

For every due task, inside the forked child process, the library must:

1. Create a fresh PSR-11 container through `ContainerFactory`.
2. Resolve the command by its class id, falling back to a no-argument command
   instance for simple commands.
3. Resolve optional `HelperSet` and `OutputInterface` services by their Symfony
   interface/class ids. Use `ConsoleOutput` only when no output service exists.
4. Convert named `SymfonyInput` arguments and options to `ArrayInput`; configured
   option names receive the Symfony `--` prefix.
5. Invoke public `Command::run()` and return its exit code to the scheduler.

The container factory must never capture a container or connection created in
the master process. Preflight discovery and validation must not instantiate the
application container.

## Application responsibilities

The application registers commands and, when needed, binds:

- `Symfony\Component\Console\Helper\HelperSet` for project helpers;
- `Symfony\Component\Console\Output\OutputInterface` for task output;
- command class ids that require constructor dependencies.

No application-specific task runner or `runScheduled()` method is required.

## Delivery checklist

- [x] Move `symfony/console` from development-only to runtime dependencies.
- [x] Add repeatable `SymfonyTask` attribute and task configuration.
- [x] Execute commands through a child-local `SymfonyCommandTaskHandler`.
- [x] Discover multiple task attribute types through a shared contract.
- [x] Cover deferred container creation, input mapping, helpers and output.
- [x] Separate named Symfony arguments/options through `SymfonyInput`.
- [x] Regenerate and verify PHP 8.2-8.5 lowest/highest dependency sets before
  release. Symfony Console 6.4.24 and 7.4 dependency sets pass.
- [x] Document application migration and a complete worker example.
- [x] Release a new pre-release version.
- [ ] Migrate mvdcake from callback wrappers.

## Acceptance criteria

- A Symfony command can be scheduled by adding only `#[SymfonyTask]`.
- Constructor-injected commands resolve from the child-local container.
- Legacy helper-based commands work when the application binds `HelperSet`.
- Output can be redirected by binding `OutputInterface` without custom runners.
- Invalid command classes, inputs, helpers and output services fail explicitly.
- Existing `CronTask` service callbacks remain backward compatible.
