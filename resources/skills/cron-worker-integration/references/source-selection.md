# Configuration source selection

Choose ownership by task family. Do not create two active owners for one task.

| Source | Prefer when | Public path |
| --- | --- | --- |
| Manual PHP | A small bootstrap owns callables or dynamic registration | `Scheduler::task()` / `serviceTask()` |
| Built-in attributes | Schedule metadata belongs beside a service or Symfony Command | `CronTask` / `SymfonyTask` plus explicit class provider |
| YAML exec | Operations owns reviewed shell jobs and per-profile values | versioned `YamlConfigLoader` catalog |
| Custom loader | Application data must map to supported concrete task configs | `CronConfigLoaderInterface` returning immutable `CronProfileMap` |
| Mixed | Ownership genuinely differs by family | explicit `CompositeCronConfigLoader` |
| Classic crontab | Existing cron must be migrated | one-time installed `crontab-to-yaml`; never a live source |

Do not encode PHP services in YAML or shell fragments in attributes. Prefer
`ClassListProvider` for a small set and `Psr4DirectoryClassProvider` for explicit
roots. A custom class source may implement `ClassProviderInterface`.

Resolve only missing facts: authoritative paths or command output; system versus
user crontab; legacy renderer output; profiles and worker timezone; command/
service directories; child-local container bootstrap; exact versus artificial
minute staggering; safe smoke task; supervisor, cutover, and rollback owners.

If paths are unknown, offer a read-only repository search for cron expressions,
crontab/YAML, scheduler registrations, command attributes, timers, and process
supervisor files. Ask before reading system/user crontabs outside the repository.
