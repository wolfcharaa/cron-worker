# Configuration source selection

Use this reference when the consumer has not already chosen how cron-worker
configuration should be owned.

## Decision table

| Primary source | Prefer when | Preserve or create |
| --- | --- | --- |
| Classic crontab | Existing entries must remain standard and independently editable | Existing five-field lines and environment assignments; load with `CrontabConfigLoader` |
| YAML profiles | Operations owns exec commands, per-environment values or generated catalogs | `crontab.yaml` conforming to the shipped JSON Schema; load with `YamlConfigLoader` |
| PHP attributes | Schedules belong beside existing container services or console-command classes | Repeatable `CronTask` attributes and an explicit class provider |
| Manual PHP API | A small bootstrap owns a few closures or dynamically assembled tasks | Calls to `task()`, `serviceTask()`, `staticTask()` or `containerTask()` |
| Mixed | Ownership genuinely differs by task family | An explicit `CompositeCronConfigLoader`; document which source owns which names |

Do not recommend attributes for arbitrary shell fragments. Do not convert an
existing standard crontab merely for stylistic consistency. Do not keep the
same task active in two sources during steady state.

## Questions to resolve

Ask only for information not already provided:

- Should current crontab remain the source of truth, or should it be converted
  once to YAML?
- Which exact files or command output are authoritative: project crontab,
  `/etc/crontab`, current-user `crontab -l`, YAML/YML, a legacy renderer, systemd
  timer, framework scheduler, or deployment manifest?
- Are system-format crontabs with a user column present?
- Which command namespaces/directories and console registrations contain jobs?
- Where can a new child-local PSR-11 container be built?
- Which profiles are required, and which tasks are shared by `any`?
- Which job is safe for the first live smoke test?
- Who owns process supervision and old-scheduler cutover?

If the user does not know the paths, offer a read-only search inside the
application repository for cron expressions, `crontab`, scheduler registration,
console command declarations, YAML files, systemd units and supervisor config.
Ask before inspecting user or system crontabs outside the repository.

## Preserve these semantics

For every discovered job capture:

- schedule and the worker-wide timezone;
- executable command, service method or static callback;
- working directory, shell and environment;
- expected Unix username or numeric effective UID;
- stdout/stderr destination;
- overlap behavior and global concurrency assumptions;
- environment/server profile;
- ordered successors that run only after exit code `0`.

Surface ambiguities instead of silently normalizing them. In particular,
classic cron `%`, mail behavior and `@reboot` are not emulated by cron-worker.
