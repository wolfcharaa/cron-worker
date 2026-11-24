# Symfony Console tasks

Use `SymfonyTask` when the work already is a Symfony Command. See the complete
declaration in [symfony-command.php](../../examples/symfony-command.php).

## Container contract

For every root or chain step, the library invokes the container factory inside
the child. The container must return the command service by its class-string id;
there is no reflection fallback. The value must be a `Command`.

`HelperSet::class` and `OutputInterface::class` are optional typed services.
Standard Console defaults are used when absent. A missing command service or
wrong type is a child failure with exit `255`, STDERR, and parent log context.

`SymfonyInput` separates named arguments and options. Declare options without
`--`; the library builds `ArrayInput`, binds the concrete command definition,
and disables interactive mode.

Master preflight does not create a child container, so command-service presence
and dynamic input validity are checked when the child starts. Cover the
production container with a separate integration smoke test.
