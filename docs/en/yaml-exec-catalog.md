# YAML exec catalog

YAML is a strict operational catalog for shell jobs only. Version `1` is
defined by [crontab.schema.json](../../resources/schema/crontab.schema.json)
and demonstrated by [cron.yaml](../../examples/cron.yaml).

`version: 1` and `profiles` are required. Empty `profiles: {}` is a valid
neutral source. A profile contains non-empty `jobs`; a job requires `name`,
`schedule`, and `command`. `then` contains equivalent exec steps without a
schedule.

`shell`, `working_directory`/`cwd`, `path`, `environment`, `overlap_policy`,
`user`, and `log` inherit from defaults/profile/root. Unknown keys and using
both `working_directory` and `cwd` are rejected.

`command` is passed to the selected shell as `shell -c` without escaping; treat
YAML as reviewed code like any shell script. `user` only checks the effective
user and never switches privileges. `log` appends stdout and stderr. The
environment inherits from the worker and catalog values override it.

During shutdown, the shell and descendants share the forked worker process
group and receive the same `SIGTERM`/`SIGKILL` lifecycle.
