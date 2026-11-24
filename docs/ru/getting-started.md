# Быстрый старт

## Результат

Вы запустите настоящий long-running worker, увидите вывод forked child не
позднее следующей минуты и корректно остановите master через `Ctrl-C`.

## Шаги

```bash
mkdir cron-heartbeat && cd cron-heartbeat
composer init --name=app/cron-heartbeat --license=proprietary --no-interaction
composer require romanfedorskij/cron:^0.2
mkdir -p bin
cp vendor/romanfedorskij/cron/examples/consumer/bin/heartbeat.php bin/
php bin/heartbeat.php
```

Пример регистрирует context-aware callable через `Scheduler::task()` и
`everyMinute()`. Ожидаемый marker:

```text
cron-heartbeat:heartbeat
```

Если marker не появился за 60 секунд, проверьте системное время, stdout и
наличие `pcntl`/`posix`. Остановите worker через `Ctrl-C`; обычный exit — `0`.
Не используйте `kill -9` как штатную остановку: master должен успеть завершить
child process groups.

Далее выберите [источник конфигурации](configuration-sources.md) и прочитайте
[операционный контракт](runtime-and-operations.md).
