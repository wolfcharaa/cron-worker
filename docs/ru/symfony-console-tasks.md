# Symfony Console задачи

Используйте `SymfonyTask`, когда работа уже является Symfony Command. Полный
declaration пример: [symfony-command.php](../../examples/symfony-command.php).

## Container contract

На каждый root/chain step библиотека вызывает container factory уже в child.
Container обязан вернуть command service по class-string id; reflection fallback
отсутствует. Объект должен быть `Command`.

`HelperSet::class` и `OutputInterface::class` — необязательные typed services.
Без них используются стандартные Console defaults. Неверный тип или отсутствие
command service — child failure с exit `255`, STDERR и parent log context.

`SymfonyInput` принимает отдельно именованные arguments и options. Options
задаются без `--`; библиотека создаёт `ArrayInput`, связывает definition
конкретной команды и выключает interactive mode.

Master preflight не создаёт child container, поэтому наличие command service и
динамическая корректность input проверяются при child start. Покройте production
container отдельным integration smoke test.
