<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Validation;

use Psr\Container\ContainerInterface;
use Wolfcharaa\Cron\Exception\InvalidTaskCallable;
use Wolfcharaa\Cron\Task\TaskExecutionContext;

final readonly class CallableTaskValidator
{
    /**
     * @param callable(mixed...): mixed $handler
     * @param array<int|string, mixed> $arguments
     */
    public function validateCallable(callable $handler, array $arguments = []): void
    {
        if (!array_is_list($arguments)) {
            throw new InvalidTaskCallable('Cron task callable arguments must be a list.');
        }

        $this->validateSignature(
            new \ReflectionFunction(\Closure::fromCallable($handler)),
            $arguments,
        );
    }

    /**
     * @param callable(ContainerInterface, TaskExecutionContext, mixed...): mixed $handler
     * @param array<int|string, mixed> $arguments
     */
    public function validateContainerCallable(callable $handler, array $arguments = []): void
    {
        if (!array_is_list($arguments)) {
            throw new InvalidTaskCallable('Container cron task callable arguments must be a list.');
        }

        $this->validateSignature(
            new \ReflectionFunction(\Closure::fromCallable($handler)),
            $arguments,
            [ContainerInterface::class, TaskExecutionContext::class],
        );
    }

    /** @param array<int|string, mixed> $arguments */
    public function validateStaticMethod(
        string $class,
        string $method,
        array $arguments = [],
        bool $containerAware = false,
    ): void {
        if (!array_is_list($arguments)) {
            throw new InvalidTaskCallable('Static cron task callable arguments must be a list.');
        }

        if (!class_exists($class)) {
            throw new InvalidTaskCallable(\sprintf('Cron task class "%s" does not exist.', $class));
        }

        if (!method_exists($class, $method)) {
            throw new InvalidTaskCallable(\sprintf(
                'Cron task method "%s::%s" does not exist.',
                $class,
                $method,
            ));
        }

        $reflection = new \ReflectionMethod($class, $method);

        if (!$reflection->isPublic() || !$reflection->isStatic()) {
            throw new InvalidTaskCallable(\sprintf(
                'Cron task method "%s::%s" must be public and static.',
                $class,
                $method,
            ));
        }

        $this->validateSignature(
            $reflection,
            $arguments,
            $containerAware ? [ContainerInterface::class, TaskExecutionContext::class] : [],
        );
    }

    /**
     * @param list<mixed> $arguments
     * @param list<class-string> $injectedTypes
     */
    private function validateSignature(
        \ReflectionFunctionAbstract $reflection,
        array $arguments,
        array $injectedTypes = [],
    ): void {
        $parameters = $reflection->getParameters();
        $suppliedCount = \count($injectedTypes) + \count($arguments);
        $name = $this->name($reflection);

        if ($suppliedCount < $reflection->getNumberOfRequiredParameters()) {
            throw new InvalidTaskCallable(\sprintf(
                'Cron task callable "%s" requires %d arguments, %d will be passed.',
                $name,
                $reflection->getNumberOfRequiredParameters(),
                $suppliedCount,
            ));
        }

        if (!$reflection->isVariadic() && $suppliedCount > \count($parameters)) {
            throw new InvalidTaskCallable(\sprintf(
                'Cron task callable "%s" accepts at most %d arguments, %d will be passed.',
                $name,
                \count($parameters),
                $suppliedCount,
            ));
        }

        foreach ($injectedTypes as $position => $type) {
            $parameter = $this->parameterAt($parameters, $position);

            if ($parameter === null) {
                throw new InvalidTaskCallable(\sprintf(
                    'Container cron task callable "%s" must accept container and execution context arguments.',
                    $name,
                ));
            }

            $this->rejectReference($name, $parameter);

            if (!$this->acceptsObjectType($parameter->getType(), $type)) {
                throw new InvalidTaskCallable(\sprintf(
                    'Argument $%s of cron task callable "%s" must accept %s.',
                    $parameter->getName(),
                    $name,
                    $type,
                ));
            }
        }

        $offset = \count($injectedTypes);

        foreach ($arguments as $position => $value) {
            $parameter = $this->parameterAt($parameters, $offset + $position);

            if ($parameter === null) {
                continue;
            }

            $this->rejectReference($name, $parameter);

            if (!$this->acceptsValue($parameter->getType(), $value)) {
                throw new InvalidTaskCallable(\sprintf(
                    'Argument $%s of cron task callable "%s" does not accept %s.',
                    $parameter->getName(),
                    $name,
                    get_debug_type($value),
                ));
            }
        }

        if (!$this->isSupportedReturnType($reflection->getReturnType())) {
            throw new InvalidTaskCallable(\sprintf(
                'Cron task callable "%s" must return int, null or void.',
                $name,
            ));
        }
    }

    /**
     * @param list<\ReflectionParameter> $parameters
     */
    private function parameterAt(array $parameters, int $position): ?\ReflectionParameter
    {
        if (isset($parameters[$position])) {
            return $parameters[$position];
        }

        $lastKey = array_key_last($parameters);
        if ($lastKey === null) {
            return null;
        }

        $last = $parameters[$lastKey];

        return $last->isVariadic() ? $last : null;
    }

    private function rejectReference(string $callable, \ReflectionParameter $parameter): void
    {
        if ($parameter->isPassedByReference()) {
            throw new InvalidTaskCallable(\sprintf(
                'Argument $%s of cron task callable "%s" must not be passed by reference.',
                $parameter->getName(),
                $callable,
            ));
        }
    }

    private function acceptsObjectType(?\ReflectionType $type, string $objectType): bool
    {
        if ($type === null) {
            return true;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if ($this->acceptsObjectType($member, $objectType)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $member) {
                if (!$this->acceptsObjectType($member, $objectType)) {
                    return false;
                }
            }

            return true;
        }

        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        $name = $type->getName();

        return $name === 'mixed'
            || $name === 'object'
            || (!$type->isBuiltin() && is_a($objectType, $name, true));
    }

    private function acceptsValue(?\ReflectionType $type, mixed $value): bool
    {
        if ($type === null || ($value === null && $type->allowsNull())) {
            return true;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if ($this->acceptsValue($member, $value)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $member) {
                if (!$this->acceptsValue($member, $value)) {
                    return false;
                }
            }

            return true;
        }

        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        $name = $type->getName();

        if (!$type->isBuiltin()) {
            return $value instanceof $name;
        }

        return match ($name) {
            'mixed' => true,
            'null' => $value === null,
            'bool' => \is_bool($value),
            'true' => $value === true,
            'false' => $value === false,
            'int' => \is_int($value),
            'float' => \is_float($value) || \is_int($value),
            'string' => \is_string($value),
            'array' => \is_array($value),
            'object' => \is_object($value),
            'callable' => \is_callable($value),
            'iterable' => is_iterable($value),
            default => false,
        };
    }

    private function isSupportedReturnType(?\ReflectionType $type): bool
    {
        if ($type === null) {
            return true;
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if (!$this->isSupportedReturnType($member)) {
                    return false;
                }
            }

            return true;
        }

        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        return \in_array($type->getName(), ['int', 'null', 'void', 'never', 'mixed'], true);
    }

    private function name(\ReflectionFunctionAbstract $reflection): string
    {
        return $reflection instanceof \ReflectionMethod
            ? $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName()
            : $reflection->getName();
    }
}
