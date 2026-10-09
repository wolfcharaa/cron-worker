<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Configuration;

use Wolfcharaa\Cron\Container\ContainerFactory;
use Wolfcharaa\Cron\Task\Handler\TaskHandler;
use Wolfcharaa\Cron\Task\OverlapPolicy;

interface TaskConfigInterface
{
    public function name(): string;

    public function overlapPolicy(): OverlapPolicy;

    public function createHandler(?ContainerFactory $containerFactory): TaskHandler;
}
