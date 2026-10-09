## Cron worker integration

Use the `cron-worker-integration` skill when installing, migrating or changing
application cron configuration. Before changing configuration, confirm the
preferred source type, authoritative cron/config paths, command or service
locations, container bootstrap and required runtime profiles. Keep the worker
entrypoint and configuration in this application; never edit the package under
`vendor/`.
