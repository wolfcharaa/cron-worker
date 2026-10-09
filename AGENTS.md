# Cron Library Guidance

Use the global `development` skill for code changes, tests, commits and
debugging. Use `library-documentation` when changing the public documentation.

## Invariants

- Keep the package compatible with PHP 8.2-8.5 on Unix-like systems with
  `pcntl` and `posix`.
- Preserve the non-blocking master process: blocking work belongs in forked
  child workers.
- Create application containers and external connections inside child scope.
- Keep configuration loaders independent and validate their combined profile
  map before task registration.
- Do not add credentials, private infrastructure values, runtime data or
  generated release artifacts.
- Run the relevant quality checks and the PHP version matrix before a release.
