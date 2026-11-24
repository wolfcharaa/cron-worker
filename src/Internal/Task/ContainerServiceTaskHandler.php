<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Task;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\TaskExecutionContext;

/** @internal */
final readonly class ContainerServiceTaskHandler implements TaskHandler
{
    /**
     * @param \Closure(): ContainerInterface $containerFactory
     * @param list<mixed> $arguments
     */
    public function __construct(
        private \Closure $containerFactory,
        private string $serviceId,
        private ?string $method,
        private array $arguments,
    ) {}

    public function execute(TaskExecutionContext $_context): mixed
    {
        $service = ($this->containerFactory)()->get($this->serviceId);
        $handler = $this->method === null ? $service : [$service, $this->method];

        if (!\is_callable($handler)) {
            throw new \UnexpectedValueException(\sprintf(
                'Container service "%s"%s is not callable.',
                $this->serviceId,
                $this->method === null ? '' : ' method "' . $this->method . '"',
            ));
        }

        return $handler(...$this->arguments);
    }
}
