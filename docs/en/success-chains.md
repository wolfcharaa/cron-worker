# Success chains

`then()` and `thenService()` form a sequence where the next step is queued only
after the previous step exits with `0`. `null` means exit `0`; valid integer
results are `0..255`. An exception or another result produces exit `255` and
stops the chain.

```php
$scheduler->task('import', $import)
    ->hourly()
    ->withoutOverlapping()
    ->thenService('index', IndexSearch::class)
    ->then('notify', $notify);
```

Each step gets a new child process and, for service/Symfony tasks, a fresh
container. Chain steps count toward global `maxConcurrency`. A step remains
queued while concurrency or overlap temporarily prevents dispatch.
`TaskExecutionContext::plannedAt` for a chain step is the time its predecessor
queued it; `startedAt` is actual dispatch time.

Shutdown starts no new successors. A stopped chain does not roll back completed
steps; idempotency and compensation belong to the application.
