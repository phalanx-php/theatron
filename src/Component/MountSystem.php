<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Component;

use Phalanx\Scope\Scope;
use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Contract\Mountable;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Reactive\DirtyBatch;

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
        /** @var Component $instance */
        $instance = new $component(...$params);
        $dirty = new DirtyBatch();

        /** @var array<string, mixed> $namedParams */
        $namedParams = [];
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $namedParams[$key] = $value;
            }
        }

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
        /** @var Screen $instance */
        $instance = new $screen(...$params);
        $dirty = new DirtyBatch();

        /** @var array<string, mixed> $namedParams */
        $namedParams = [];
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $namedParams[$key] = $value;
            }
        }

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
}
