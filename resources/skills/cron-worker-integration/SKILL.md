---
name: cron-worker-integration
description: Integrate romanfedorskij/cron 0.2 into a real application or migrate an existing cron, framework scheduler, YAML catalog, systemd timer, or legacy renderer. Use for application-owned worker composition, schedule inventory, source selection, child-local PSR-11 wiring, preflight, safe cutover, and rollback. Do not use for developing the cron package itself.
---

# Cron Worker Integration

Work in the consumer application repository. Use only the package public API,
installed CLI, schema, examples, and documentation. Never edit `vendor/`.

## Confirm ownership before changing configuration

If the user has not already supplied the answer, ask one compact group of at
most three questions covering:

1. which source should own each task family: manual PHP, built-in attributes,
   YAML exec, custom loader, or an intentional mix;
2. exact authoritative paths/commands and required profiles/timezone;
3. child-local PSR-11 bootstrap, process supervisor, safe smoke task, cutover
   owner, and rollback authority.

Use [source-selection.md](references/source-selection.md) when ownership is
unclear. Classic crontab is migration input, never a live loader.

## Inventory the application

- Read application instructions, Composer config, worker/console entrypoints,
  scoped schedule sources, container bootstrap, and supervisor config.
- Record task name, expression, timezone, profile, command/service, cwd, shell,
  environment, effective user, output, overlap, expected duration, and ordered
  successors.
- Separate Symfony Commands, ordinary PSR-11 services, and shell fragments.
- Flag destructive/external work and distinguish business-time offsets from
  offsets that merely serialized a blocking legacy scheduler.
- Preserve behavior unless the user explicitly approves a semantic change.

Read [migration-map.md](references/migration-map.md) before implementation.

## Implement application-owned composition

1. Require a released `^0.2` version and confirm it with `composer show`.
2. Add an application-owned worker; do not copy package internals.
3. Build a fresh PSR-11 container inside the factory invoked after fork. Do not
   capture master-opened database, Redis, socket, or framework resources.
4. Use only selected public sources. Use `CompositeCronConfigLoader` only when
   multiple owners are deliberate and task names are disjoint.
5. Select one profile with root `SchedulerOptions`; keep stage and production
   declarations separate.
6. Preserve schedule, timezone, environment, cwd, shell, effective-user check,
   output, overlap, and concurrency. Model real ordering with success chains.
7. Preflight through `Scheduler::load()` using production options and factory;
   do not invoke `run()` during preflight.

Keep the old scheduler active until an isolated smoke succeeds. Never disable a
crontab/timer, modify a supervisor, or execute dangerous work without explicit
authorization.

## Verify and hand off

- Lint application-owned PHP/YAML and validate YAML against the packaged schema.
- Preflight every deployed profile and compare expected task names.
- Run a harmless observable task; verify exit, logs, child-local container,
  overlap, concurrency, `SIGTERM`, grace, and restart policy.
- Present exact old entries to disable, the cutover sequence, observation
  window, and an executable rollback. Execute cutover only when requested.

Report source ownership, inspected paths, mapping, files changed, checks run,
remaining runtime evidence, and whether cutover has or has not occurred.
