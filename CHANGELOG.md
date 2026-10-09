# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

- PSR Container compatibility now includes both `^1.1` and `^2.0`.
