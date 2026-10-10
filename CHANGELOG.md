# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-10-10

### Changed

- Promoted the tested `0.1.0-rc.3` API to the first stable release without
  public contract changes.
- Release checks now resolve test fixtures through PSR-4 and explicitly declare
  every runtime and test dependency used by the package.

## [0.1.0-rc.3] - 2026-10-10

### Added

- `SymfonyInput` for configuring named Symfony Console arguments and options
  through the command's standard `InputInterface`.
- Typed `EveryTime` and `CronExpression` schedules in task attributes.

### Changed

- `SymfonyTask` and `SymfonyCommandTaskConfig` now accept `input` instead of
  positional handler-style `arguments`; Symfony tasks always run non-interactively.

## [0.1.0-rc.2] - 2026-10-10

### Added

- Native Symfony Console task attributes that resolve commands, helper sets and
  output from a fresh child-process PSR-11 container and execute commands through
  `Command::run()` without application callback wrappers.
- Multi-profile task definitions and attributes for sharing identical task
  settings without duplicating configuration.
- A tracked Docker Compose override for resolving Packagist during local
  dependency and PHP-version matrix runs.

### Changed

- Cron timezone is configured once through `SchedulerOptions`; per-task,
  attribute, YAML and `CRON_TZ` schedule timezones are no longer accepted.

## [0.1.0-rc.1] - 2026-10-10

### Added

- Packaged `cron-worker-integration` Codex skill for guided discovery,
  configuration-source selection and safe application migration without
  reading library internals.

## [0.1.0-rc] - 2026-10-09

### Added

- Static and container-callback chain task configs with preflight callable and
  profile-map validation before scheduler registration.
- Runtime platform contract tests for PCNTL and POSIX support.
- Isolated PHP 8.2-8.5 test matrix for lowest and highest dependency sets.
- Copyable application worker bootstrap for composing attribute, YAML,
  classic crontab and manual task sources.
- Extensible cron configuration loaders for explicit attribute discovery and
  classic crontab files without library-specific directives.
- Native PHP YAML configuration loading with mvdcake catalog compatibility,
  separate profile settings, one-shot exec chains and a published JSON Schema.
- A Symfony Console development command for converting classic crontab lists
  into loadable YAML configuration.
- Profile-specific attribute pipelines through `ChainConfig`, service task
  configs and external exec task configs.
- Separate `CronProfileConfig` maps so every profile owns its complete task
  settings instead of sharing one definition across several profiles.
- Named scheduler profiles with arbitrary catalog-compatible names and an
  `any` wildcard for shared tasks.
- Cron profile maps with duplicate task validation per deployment plan.
- Repeatable `CronTask` attributes and class-provider discovery for compiling
  service commands into cron profile maps.
- PSR-11 container-aware handlers with child-process container factories and
  one-shot success chains.
- Fluent task and schedule builders with typed minute, hour, day and month
  interval configurations.
- Configurable `CronExpression` value object with parsing and serialization of
  individual cron rules.
- Revolt-based cron scheduler runtime.
- PCNTL process supervisor with non-blocking child reaping.
- Cron schedules, overlap protection, concurrency limits and graceful shutdown.

### Changed

- Exec tasks accept either a Unix username or a numeric effective UID, including
  container users that are absent from `/etc/passwd`.
- Release sources no longer include unrelated application templates or
  framework-specific Codex skills.
- Library documentation is split into task-oriented guides, contracts and
  copyable examples, with README kept as the navigation and quick-start page.
- PSR Container compatibility now includes both `^1.1` and `^2.0`.
