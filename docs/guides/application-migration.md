# Application migration with Codex

Пакет поставляет skill `cron-worker-integration`, который проводит интеграцию
со стороны приложения: сначала выясняет выбранный способ конфигурации и пути к
текущим cron-источникам, затем строит карту задач и использует публичный API
библиотеки. Читать или изменять `vendor/.../src` для этого не требуется.

## Подключение skill в приложении

После установки пакета скопируйте его в project-local каталог Codex:

```shell
mkdir -p .agents/skills
cp -R \
  vendor/romanfedorskij/cron/.agents/skills/cron-worker-integration \
  .agents/skills/
```

Добавьте в `AGENTS.md` приложения правила из
[`AGENTS.snippet.md`](../../.agents/skills/cron-worker-integration/assets/AGENTS.snippet.md).
В source checkout библиотеки skill уже доступен напрямую через
[`SKILL.md`](../../.agents/skills/cron-worker-integration/SKILL.md).

## Запуск диалога

```text
Используй $cron-worker-integration и помоги перенести cron этого приложения.
```

Если направление ещё не задано, skill попросит выбрать основной источник:

- оставить классический crontab без изменения формата;
- перенести exec-задачи в YAML с отдельными profiles;
- поставить repeatable attributes над существующими command/service классами;
- собрать несколько задач manual PHP API;
- явно объединить разные источники через composite loader.

Он также запросит пути к crontab/YAML или legacy-конфигурации, каталоги команд,
PSR-11 container bootstrap и необходимые runtime profiles. Доступ к
`crontab -l` или `/etc/crontab` не предполагается автоматически.

## Что получится

Skill формирует инвентарь с расписанием, timezone, environment, cwd, shell,
user/UID, output, overlap и success-chain связями, после чего:

1. создаёт application-owned `bin/cron.php`;
2. настраивает только выбранные loaders;
3. сохраняет раздельные stage/prod/server profiles;
4. создаёт свежий container scope после fork;
5. проверяет карту до запуска event loop;
6. предлагает безопасный smoke test и отдельный план cutover/rollback.

Существующий cron, systemd timer или supervisor не отключается без явного
подтверждения пользователя.
