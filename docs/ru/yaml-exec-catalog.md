# YAML exec-каталог

YAML — строгий operational catalog только для shell jobs. Контракт версии `1`
описан схемой [crontab.schema.json](../../resources/schema/crontab.schema.json)
и примером [cron.yaml](../../examples/cron.yaml).

Обязательны `version: 1` и `profiles`. Пустой `profiles: {}` — валидный neutral
source. Profile содержит непустой `jobs`; job требует `name`, `schedule`,
`command`. `then` содержит такие же exec steps без schedule.

`shell`, `working_directory`/`cwd`, `path`, `environment`, `overlap_policy`,
`user` и `log` наследуются от defaults/profile/root. Неизвестные keys и
одновременные `working_directory`/`cwd` запрещены.

`command` передаётся выбранному shell как `shell -c` без escaping: YAML должен
считаться кодом с тем же review, что shell script. `user` только сверяет
effective user и не делает privilege switch. `log` append-ит stdout и stderr.
Environment наследуется от worker и перекрывается catalog values.

При остановке shell и его потомки находятся в process group forked worker и
получают общий `SIGTERM`/`SIGKILL` lifecycle.
