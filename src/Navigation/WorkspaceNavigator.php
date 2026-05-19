<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Navigation;

use Phalanx\Theatron\Component\MountedComponent;
use Phalanx\Theatron\Component\MountedScreen;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Contract\Screen;

/**
 * Concrete Navigator implementation.
 *
 * Workspaces (Screen classes) are mounted once and persist across go() switches.
 * The inactive workspace is hidden — its signals and subscriptions remain live.
 *
 * Overlays (any Component class) are transient. Each push creates a fresh
 * MountedComponent. Dismiss disposes the component immediately.
 */
final class WorkspaceNavigator implements Navigator
{
    /** @var array<class-string<Screen>, MountedScreen> */
    private array $workspaces = [];

    /** @var list<MountedComponent> */
    private array $overlayStack = [];

    /** @var class-string<Screen> */
    private string $activeScreen;

    /**
     * @param class-string<Screen> $initialScreen
     */
    public function __construct(
        private(set) MountSystem $mountSystem,
        string $initialScreen,
    ) {
        $this->activeScreen = $initialScreen;
        $this->workspaces[$initialScreen] = $this->mountSystem->mountScreen($initialScreen);
    }

    /**
     * @param class-string<Screen> $screen
     */
    public function go(string $screen): void
    {
        if ($this->activeScreen === $screen) {
            return;
        }

        $this->activeScreen = $screen;

        if (!isset($this->workspaces[$screen])) {
            $this->workspaces[$screen] = $this->mountSystem->mountScreen($screen);
        }
    }

    /**
     * @param class-string<\Phalanx\Theatron\Contract\Component> $component
     */
    public function overlay(string $component, mixed ...$params): void
    {
        $mounted = $this->mountSystem->mount($component, ...$params);
        $this->overlayStack[] = $mounted;
    }

    public function dismiss(): void
    {
        if ($this->overlayStack === []) {
            return;
        }

        $top = array_pop($this->overlayStack);
        $top->dispose();
    }

    public function dismissAll(): void
    {
        $stack = $this->overlayStack;
        $this->overlayStack = [];

        foreach (array_reverse($stack) as $overlay) {
            $overlay->dispose();
        }
    }

    public function active(): string
    {
        return $this->activeScreen;
    }

    public function activeWorkspace(): MountedScreen
    {
        return $this->workspaces[$this->activeScreen];
    }

    /** @return list<MountedComponent> */
    public function overlays(): array
    {
        return $this->overlayStack;
    }

    public function hasOverlays(): bool
    {
        return $this->overlayStack !== [];
    }
}
