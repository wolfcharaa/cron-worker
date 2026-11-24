# Миграция system cron

Установленный `vendor/bin/crontab-to-yaml` преобразует поддерживаемый subset в
YAML v1 и затем проверяет результат тем же loader-ом.

```bash
crontab -l | vendor/bin/crontab-to-yaml --profile=prod > cron.yaml
vendor/bin/crontab-to-yaml /etc/crontab --system --output=cron.yaml --profile=prod
```

Поддерживаются пять schedule fields, `@yearly`/`@annually`, `@monthly`,
`@weekly`, `@daily`/`@midnight`, `@hourly`, environment assignments и system
user column. Fail-closed отклоняются `@reboot`, `CRON_TZ`, `%` newline semantics,
неизвестные shortcuts и malformed lines. `SHELL`, `HOME` и environment
переносятся; system user становится проверкой effective user, не switch.

## Безопасный cutover

1. Сохраните исходный crontab и владельца.
2. Конвертируйте и внимательно проверьте commands, paths, user, environment,
   output и timezone; предупреждение CLI не является доказательством parity.
3. Выполните preflight `YamlConfigLoader` + `Scheduler::load()` для profile.
4. Запустите worker параллельно с отключёнными/безопасными тестовыми jobs и
   проверьте logs, overlap, shutdown и restart policy.
5. Получите явное authority на cutover, отключите старый cron ровно один раз и
   наблюдайте полный schedule interval.
6. Rollback: остановите worker, восстановите сохранённый crontab и подтвердите
   отсутствие двойного запуска.
