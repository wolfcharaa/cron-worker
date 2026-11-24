# Cron

[English](README.en.md)

`romanfedorskij/cron` — Unix-библиотека для постоянно работающего cron-worker:
она вычисляет расписания с минутной точностью, запускает каждую задачу в
отдельном процессе и управляет overlap, цепочками и корректной остановкой.

Пакет подходит, когда приложению нужны PHP-service, Symfony Command или
shell-задачи с единым процессом-планировщиком. Он не предназначен для Windows,
sub-minute таймеров, распределённой очереди, переключения Unix user или
Kubernetes one-shot jobs.

Требования: PHP 8.2–8.5, Unix и расширения `pcntl`, `posix`, `ctype`.

## Установка

```bash
composer require romanfedorskij/cron:^0.2
```

## Первый наблюдаемый запуск

Создайте чистый проект и скопируйте поставляемый пример:

```bash
mkdir cron-heartbeat && cd cron-heartbeat
composer init --name=app/cron-heartbeat --license=proprietary --no-interaction
composer require romanfedorskij/cron:^0.2
mkdir -p bin
cp vendor/romanfedorskij/cron/examples/consumer/bin/heartbeat.php bin/
php bin/heartbeat.php
```

Не позднее следующей границы минуты forked child напечатает:

```text
cron-heartbeat:heartbeat
```

Нажмите `Ctrl-C`: master отправит активным process groups `SIGTERM`, дождётся
grace period и завершится; зависшие группы после grace получают `SIGKILL`.

## Дальше

- [Быстрый старт](docs/ru/getting-started.md)
- [Источники конфигурации](docs/ru/configuration-sources.md)
- [Symfony Console](docs/ru/symfony-console-tasks.md)
- [YAML exec-каталог](docs/ru/yaml-exec-catalog.md)
- [Success chains](docs/ru/success-chains.md)
- [Расписания и profiles](docs/ru/scheduling-and-profiles.md)
- [Runtime и эксплуатация](docs/ru/runtime-and-operations.md)
- [Миграция system cron](docs/ru/migrating-system-cron.md)
- [Публичный API](docs/ru/public-api.md)
- [Миграция 0.1 → 0.2](docs/ru/migration-0.1-to-0.2.md)

Лицензия: [MIT](LICENSE).
