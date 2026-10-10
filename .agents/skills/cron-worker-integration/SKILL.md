---
name: cron-worker-integration
description: Discover an application's existing cron jobs and migrate or integrate them with romanfedorskij/cron using classic crontab, YAML profiles, PHP attributes, manual registration, or a deliberate combination. Use when installing cron-worker into a real application, replacing a legacy scheduler or Lua renderer, attaching schedules to existing commands or services, or planning a safe cron cutover. Do not use for developing the cron-worker library itself.
---

# Cron Worker Integration

Integrate the package from the consumer application's repository. Use only its
public API and shipped documentation; never edit files under `vendor/` or
rediscover the API by reading library internals unless published contracts are
insufficient and the user asks for investigation.

## Get direction before changing configuration

If the user has not already supplied these choices, ask one compact set of no
more than three questions and wait for the answers:

1. Which configuration style should be primary: unchanged classic crontab,
   YAML profiles, PHP attributes over command/service classes, manual PHP API,
   or an intentional mix?
2. Where should existing schedules be read from? Request project-relative
   paths to crontab, YAML/YML, legacy generated config or scheduler bootstrap.
   Ask whether the current user's `crontab -l` or a system crontab is also in
   scope; do not assume access to either.
3. Where are current commands/services and the PSR-11 container bootstrap, and
   which runtime profiles or environments must be preserved?

Use [references/source-selection.md](references/source-selection.md) to refine
the questions and recommend a source when the user is unsure. A source choice
changes ownership and deployment, so do not silently choose one and start a
migration.

## Inventory the consumer application

After receiving direction:

- read the application's `AGENTS.md`, `composer.json`, console entrypoints,
  container bootstrap, process-supervisor configuration and only the cron paths
  placed in scope;
- build a table of task name, expression, runtime timezone, profile, command or service,
  working directory, environment, Unix user/UID, output target, overlap policy
  and success-chain relationship;
- distinguish Symfony Console `Command` classes, ordinary services and shell
  fragments that must remain exec tasks; for Symfony commands map CLI arguments
  and options to their names from `configure()`;
- mark minute offsets that may exist only to serialize a blocking legacy cron,
  separately from exact wall-clock requirements;
- flag destructive, externally visible or long-running jobs before any live
  execution;
- identify duplicate schedules and conflicts, but preserve behavior unless the
  user approves a semantic change.

Read [references/migration-map.md](references/migration-map.md) when converting
the inventory into cron-worker configuration.

## Implement in the application

1. Require a specific released package version and confirm it with
   `composer show romanfedorskij/cron`.
2. Create an application-owned worker entrypoint such as `bin/cron.php`; use the
   shipped `examples/application-worker.php` as composition guidance, not as a
   file to modify in `vendor/`.
3. Build a fresh PSR-11 container inside the child-process factory. Do not
   capture a master-process container or open connections before fork.
4. Configure only the loader types selected with the user. Use
   `CompositeCronConfigLoader` only when mixed ownership is intentional.
5. Map environment/server schedules to separate profiles and select one through
   `SchedulerOptions`; do not merge stage and production definitions.
6. Preserve schedule, the worker-wide timezone, environment, cwd, shell,
   user/UID, log and overlap semantics. Simplify artificial minute staggering
   only after confirming jobs are independent and `maxConcurrency` covers the
   resulting peak; preserve exact business times. Treat real dependencies as
   one-shot success chains, not delayed scheduled roots.
7. Let `Scheduler::load()` preflight the selected profile before the Revolt
   event loop starts.

Keep the existing scheduler active until an isolated smoke test succeeds. Do
not delete crontab entries, disable timers, modify a supervisor, or run a
dangerous task without explicit authorization.

## Verify and hand off

- Syntax-check the application-owned worker and configuration files.
- Verify the selected profile contains the expected task names and no duplicate
  roots.
- Start with a harmless, observable task or a dedicated smoke profile.
- Confirm exit code, log destination, child-local container creation, overlap
  behavior and graceful shutdown.
- Present a cutover plan with the exact old scheduler entries to disable and a
  rollback step. Perform the cutover only when requested.

Report the chosen configuration owner, inspected source paths, task mapping,
files changed, validation performed, and remaining runtime checks.
