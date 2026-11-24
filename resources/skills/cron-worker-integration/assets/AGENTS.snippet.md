## Cron worker integration

Use the `cron-worker-integration` skill when installing or migrating
`romanfedorskij/cron`. Confirm source ownership, authoritative schedule paths,
profiles/timezone, child-local PSR-11 bootstrap, supervision, cutover authority,
and rollback. Keep all worker/config changes in this application; never edit
the package under `vendor/`.
