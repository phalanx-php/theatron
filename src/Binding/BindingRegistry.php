<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Binding;

use Phalanx\Theatron\Input\KeyEvent;

/**
 * Layered key-binding resolver.
 *
 * Resolution priority (top wins):
 *   overlay stack (most recent first)
 *   → active screen
 *   → global
 *
 * Layers are additive — pushing an overlay does not remove screen or global
 * bindings. The first layer to match the event wins; unmatched events return
 * null so the caller can fall through to normal input handling.
 */
final class BindingRegistry
{
    /** @var list<Binding> */
    private array $global = [];

    /** @var array<class-string, list<Binding>> */
    private array $screenBindings = [];

    /** @var string|null */
    private ?string $activeScreen = null;

    /** @var list<array{id: string, bindings: list<Binding>}> */
    private array $overlayStack = [];

    // -------------------------------------------------------------------------
    // Layer management

    /** @param list<Binding> $bindings */
    public function setGlobal(array $bindings): void
    {
        $this->global = $bindings;
    }

    /**
     * Register bindings for a named screen class. Calling again for the same
     * screen replaces the existing set.
     *
     * @param class-string $screen
     * @param list<Binding> $bindings
     */
    public function setScreen(string $screen, array $bindings): void
    {
        $this->screenBindings[$screen] = $bindings;
    }

    /**
     * Activate a screen by class name so its bindings participate in resolution.
     *
     * @param class-string $screen
     */
    public function activateScreen(string $screen): void
    {
        $this->activeScreen = $screen;
    }

    /**
     * Push a named overlay layer onto the stack.
     *
     * @param list<Binding> $bindings
     */
    public function pushOverlay(string $id, array $bindings): void
    {
        $this->overlayStack[] = ['id' => $id, 'bindings' => $bindings];
    }

    /** Pop the top overlay layer. No-op if the stack is empty. */
    public function popOverlay(): void
    {
        if ($this->overlayStack !== []) {
            array_pop($this->overlayStack);
        }
    }

    /** Remove all overlay layers at once. */
    public function clearOverlays(): void
    {
        $this->overlayStack = [];
    }

    // -------------------------------------------------------------------------
    // Resolution

    /**
     * Walk layers top-down and return the first Binding that matches the event.
     * Returns null when no binding claims the event.
     */
    public function resolve(KeyEvent $event): ?Binding
    {
        // Overlays take priority — most recently pushed overlay is checked first.
        foreach (array_reverse($this->overlayStack) as $layer) {
            foreach ($layer['bindings'] as $binding) {
                if ($binding->matches($event)) {
                    return $binding;
                }
            }
        }

        // Active screen bindings.
        if ($this->activeScreen !== null) {
            $screenBindings = $this->screenBindings[$this->activeScreen] ?? [];

            foreach ($screenBindings as $binding) {
                if ($binding->matches($event)) {
                    return $binding;
                }
            }
        }

        // Global fallback.
        foreach ($this->global as $binding) {
            if ($binding->matches($event)) {
                return $binding;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Introspection

    /**
     * Return all bindings visible in the current layer configuration.
     * Useful for rendering a key-hint bar. Higher-priority layers shadow
     * lower-priority ones for the same key combo.
     *
     * @return list<Binding>
     */
    public function activeBindings(): array
    {
        // Collect in resolution order; track seen combos to apply shadowing.
        $seen = [];
        $result = [];

        $addIfUnseen = static function (Binding $b) use (&$seen, &$result): void {
            $key = self::comboKey($b);

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $b;
            }
        };

        foreach (array_reverse($this->overlayStack) as $layer) {
            foreach ($layer['bindings'] as $binding) {
                $addIfUnseen($binding);
            }
        }

        if ($this->activeScreen !== null) {
            foreach ($this->screenBindings[$this->activeScreen] ?? [] as $binding) {
                $addIfUnseen($binding);
            }
        }

        foreach ($this->global as $binding) {
            $addIfUnseen($binding);
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Internals

    private static function comboKey(Binding $b): string
    {
        $k = $b->key instanceof \Phalanx\Theatron\Input\Key ? $b->key->value : $b->key;

        return ($b->ctrl ? 'c' : '-') . ($b->alt ? 'a' : '-') . ($b->shift ? 's' : '-') . ':' . $k;
    }
}
