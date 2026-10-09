<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Task\Handler;

use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

final readonly class ContainerServiceHandler implements TaskHandler
{
    /**
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(
        private ContainerFactory $containerFactory,
        private string $serviceId,
        private ?string $method = null,
        private array $arguments = [],
    ) {
        if ($this->serviceId === '') {
            throw new \InvalidArgumentException('Container service id must not be empty.');
        }

        if ($this->method === '') {
            throw new \InvalidArgumentException('Container service method must not be empty.');
        }
    }

    public function execute(TaskExecutionContext $context): mixed
    {
        $service = $this->containerFactory->create()->get($this->serviceId);
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
