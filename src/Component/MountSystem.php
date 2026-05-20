<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Component;

use Phalanx\Exception\ServiceNotFoundException;
use Phalanx\Scope\Scope;
use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Contract\Mountable;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Reactive\DirtyBatch;
use ReflectionClass;
use ReflectionNamedType;

class MountSystem
{
    /** @var list<MountedComponent> */
    private array $mounted = [];

    public function __construct(
        private(set) Scope $scope,
        private ?TaskScope $taskScope = null,
    ) {
    }

    /**
     * @template T of Component
     * @param class-string<T> $component
     */
    public function mount(string $component, mixed ...$params): MountedComponent
    {
        $namedParams = self::extractNamedParams($params);
        /** @var Component $instance */
        $instance = self::resolveInstance($component, $namedParams, $this->scope);
        $dirty = new DirtyBatch();

        $scanResult = SignalScanner::scan($instance, $dirty, $namedParams);
        $mounted = new MountedComponent($instance, $dirty, $scanResult);
        $this->mounted[] = $mounted;

        if ($this->taskScope !== null) {
            $this->taskScope->onDispose(static function () use ($mounted): void {
                $mounted->dispose();
            });
        }

        if ($instance instanceof Mountable && $this->taskScope !== null) {
            $instance->onMount($this->taskScope);
        }

        return $mounted;
    }

    /**
     * @template T of Screen
     * @param class-string<T> $screen
     */
    public function mountScreen(string $screen, mixed ...$params): MountedScreen
    {
        $namedParams = self::extractNamedParams($params);
        /** @var Screen $instance */
        $instance = self::resolveInstance($screen, $namedParams, $this->scope);
        $dirty = new DirtyBatch();

        $scanResult = SignalScanner::scan($instance, $dirty, $namedParams);
        $mounted = new MountedScreen($instance, $dirty, $scanResult);

        if ($this->taskScope !== null) {
            $this->taskScope->onDispose(static function () use ($mounted): void {
                $mounted->dispose();
            });
        }

        if ($instance instanceof Mountable && $this->taskScope !== null) {
            $instance->onMount($this->taskScope);
        }

        return $mounted;
    }

    public function disposeAll(): void
    {
        $mounted = $this->mounted;
        $this->mounted = [];

        foreach ($mounted as $component) {
            $component->dispose();
        }
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $namedParams
     */
    private static function resolveInstance(string $class, array $namedParams, Scope $scope): object
    {
        $ref = new ReflectionClass($class);
        $constructor = $ref->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $class();
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $namedParams)) {
                $args[$name] = $namedParams[$name];
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                continue;
            }

            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                /** @var class-string $typeName */
                $typeName = $type->getName();
                try {
                    $args[$name] = $scope->service($typeName);
                    continue;
                } catch (ServiceNotFoundException) {
                }
            }
        }

        return new $class(...$args);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>
     */
    private static function extractNamedParams(array $params): array
    {
        $named = [];
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $named[$key] = $value;
            }
        }

        return $named;
    }
}
