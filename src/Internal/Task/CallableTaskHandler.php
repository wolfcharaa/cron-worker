<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Internal\Task;

use Wolfcharaa\Cron\Exception\InvalidCronConfiguration;
use Wolfcharaa\Cron\TaskExecutionContext;

/** @internal */
final readonly class CallableTaskHandler implements TaskHandler
{
    /** @var (\Closure(): mixed)|(\Closure(TaskExecutionContext): mixed) */
    private \Closure $callable;

    private bool $receivesContext;

    /** @param (callable(): mixed)|(callable(TaskExecutionContext): mixed) $callable */
    public function __construct(callable $callable, string $path)
    {
        $reflection = self::reflect($callable);
        $parameters = $reflection->getParameters();

        if (\count($parameters) > 1) {
            throw InvalidCronConfiguration::at('<manual>', $path, 'callable must accept zero arguments or one TaskExecutionContext.');
        }

        $this->receivesContext = $parameters !== [];

        $parameter = $parameters[0] ?? null;

        if ($this->receivesContext && ($parameter === null || !self::acceptsContext($parameter->getType()))) {
            throw InvalidCronConfiguration::at('<manual>', $path, 'the optional callable argument must accept TaskExecutionContext.');
        }

        if (!self::supportsResult($reflection->getReturnType())) {
            throw InvalidCronConfiguration::at('<manual>', $path, 'callable return type must be int, null, void, never or mixed.');
        }

        $this->callable = \Closure::fromCallable($callable);
    }

    public function execute(TaskExecutionContext $context): mixed
    {
        return $this->receivesContext ? ($this->callable)($context) : ($this->callable)();
    }

    /** @param (callable(): mixed)|(callable(TaskExecutionContext): mixed) $callable */
    private static function reflect(callable $callable): \ReflectionFunctionAbstract
    {
        if (\is_array($callable)) {
            return new \ReflectionMethod($callable[0], $callable[1]);
        }

        if (\is_string($callable) && str_contains($callable, '::')) {
            return new \ReflectionMethod($callable);
        }

        if (\is_object($callable) && !$callable instanceof \Closure) {
            return new \ReflectionMethod($callable, '__invoke');
        }

        return new \ReflectionFunction(\Closure::fromCallable($callable));
    }

    private static function acceptsContext(?\ReflectionType $type): bool
    {
        if ($type === null) {
            return true;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if (self::acceptsContext($member)) {
                    return true;
                }
            }

            return false;
        }

        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        if ($type->isBuiltin()) {
            return \in_array($type->getName(), ['mixed', 'object'], true);
        }

        return is_a(TaskExecutionContext::class, $type->getName(), true);
    }

    private static function supportsResult(?\ReflectionType $type): bool
    {
        if ($type === null) {
            return true;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if (!self::supportsResult($member)) {
                    return false;
                }
            }

            return true;
        }

        return $type instanceof \ReflectionNamedType
            && \in_array($type->getName(), ['int', 'null', 'void', 'never', 'mixed'], true);
    }
}
