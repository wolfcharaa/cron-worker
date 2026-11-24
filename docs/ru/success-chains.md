# Success chains

`then()` и `thenService()` создают последовательность, где следующий step
ставится в очередь только после exit `0` предыдущего. `null` означает exit `0`;
допустимый integer result — `0..255`. Exception или другой result даёт exit
`255` и останавливает цепочку.

```php
$scheduler->task('import', $import)
    ->hourly()
    ->withoutOverlapping()
    ->thenService('index', IndexSearch::class)
    ->then('notify', $notify);
```

Каждый step получает новый child process и, для service/Symfony task, новый
container. Chain steps учитываются в общем `maxConcurrency`. Если лимит или
overlap временно не позволяет старт, step остаётся в очереди. `plannedAt` в
`TaskExecutionContext` chain step — время постановки successor в очередь;
`startedAt` — фактический dispatch.

Shutdown не запускает новые successors. Несостоявшаяся цепочка не откатывает
уже выполненные steps; идемпотентность и compensation принадлежат приложению.
