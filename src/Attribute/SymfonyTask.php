<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Attribute;

use Symfony\Component\Console\Command\Command;
use Wolfcharaa\Cron\Configuration\ChainConfig;
use Wolfcharaa\Cron\Configuration\Profile;
use Wolfcharaa\Cron\Configuration\SymfonyCommandTaskConfig;
use Wolfcharaa\Cron\Registry\CronTaskDefinition;
use Wolfcharaa\Cron\Schedule\CronExpression;
use Wolfcharaa\Cron\Task\OverlapPolicy;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class SymfonyTask implements CronTaskAttribute
{
    public CronExpression $expression;

    /** @var list<Profile> */
    public array $profiles;

    public ChainConfig $chain;

    /**
     * @param Profile|string|\BackedEnum|list<Profile|string|\BackedEnum> $profiles
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(
        string $schedule,
        Profile|string|\BackedEnum|array $profiles = [Profile::DEFAULT],
        public ?string $name = null,
        public array $arguments = [],
        public OverlapPolicy $overlapPolicy = OverlapPolicy::Forbid,
        ?ChainConfig $chain = null,
    ) {
        if ($this->name === '') {
            throw new \InvalidArgumentException('Symfony task attribute name must not be empty.');
        }

        $this->expression = new CronExpression($schedule);
        $this->profiles = Profile::normalizeMany($profiles);
        $this->chain = $chain ?? new ChainConfig();
    }

    /** @param class-string $targetClass */
    public function toDefinition(string $targetClass): CronTaskDefinition
    {
        if (!is_a($targetClass, Command::class, true)) {
            throw new \LogicException(\sprintf(
                'Symfony cron task "%s" must extend %s.',
                $targetClass,
                Command::class,
            ));
        }

        return new CronTaskDefinition(
            task: new SymfonyCommandTaskConfig(
                commandId: $targetClass,
                arguments: $this->arguments,
                taskName: $this->name,
                overlapPolicy: $this->overlapPolicy,
            ),
            expression: $this->expression,
            profiles: $this->profiles,
            chain: $this->chain,
        );
    }
}
