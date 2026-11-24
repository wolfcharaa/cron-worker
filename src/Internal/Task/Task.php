<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Task;

use Wolfcharaa\Cron\Task\OverlapPolicy;
use Wolfcharaa\Cron\TaskExecutionContext;

/** @internal */
final readonly class Task
{
    public function __construct(
        public string $name,
        public TaskHandler $handler,
        public OverlapPolicy $overlapPolicy,
        public ?self $next = null,
    ) {}

    public function execute(TaskExecutionContext $context): int
    {
        $result = $this->handler->execute($context);

        if ($result === null) {
            return 0;
        }

        if (\is_int($result) && $result >= 0 && $result <= 255) {
            return $result;
        }

        throw new \UnexpectedValueException(\sprintf(
            'Task "%s" must return null or an integer exit code between 0 and 255; %s returned.',
            $this->name,
            get_debug_type($result),
        ));
    }
}
