# Migrating system cron

The installed `vendor/bin/crontab-to-yaml` converts the supported subset to YAML
v1 and validates the result through the same loader.

```bash
crontab -l | vendor/bin/crontab-to-yaml --profile=prod > cron.yaml
vendor/bin/crontab-to-yaml /etc/crontab --system --output=cron.yaml --profile=prod
```

Supported input includes five schedule fields, `@yearly`/`@annually`,
`@monthly`, `@weekly`, `@daily`/`@midnight`, `@hourly`, environment assignments,
and the system user column. The parser fails closed on `@reboot`, `CRON_TZ`, `%`
newline semantics, unknown shortcuts, and malformed lines. `SHELL`, `HOME`, and
environment are retained; a system user becomes an effective-user check, not a
privilege switch.

## Safe cutover

1. Save the original crontab and owner.
2. Convert and review commands, paths, user, environment, output, and timezone;
   the CLI warning is not proof of parity.
3. Preflight the profile with `YamlConfigLoader` and `Scheduler::load()`.
4. Start the worker with disabled/safe test jobs and verify logs, overlap,
   shutdown, and restart policy.
5. Obtain explicit cutover authority, disable old cron exactly once, and
   observe a full schedule interval.
6. Roll back by stopping the worker, restoring the saved crontab, and checking
   that duplicate execution is absent.
