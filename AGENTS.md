# Cron Library Guidance

Use the global `development` skill for code changes, tests, commits and
debugging. Use `library-documentation` when changing the public documentation.
The packaged `resources/skills/cron-worker-integration` skill is a consumer
asset, not an active instruction for library development. Install/use it only
when explicitly integrating this package into an application.

## Invariants

- Keep the package compatible with PHP 8.2-8.5 on Unix-like systems with
  `pcntl` and `posix`.
- Preserve the bounded sequential PCNTL master loop: blocking application work
  belongs in forked child workers, and master waits remain at most 250 ms.
- Create application containers and external connections inside child scope.
- Keep configuration loaders independent and validate their combined profile
  map before task registration.
- Do not add credentials, private infrastructure values, runtime data or
  generated release artifacts.
- Run the relevant quality checks and the PHP version matrix before a release.
