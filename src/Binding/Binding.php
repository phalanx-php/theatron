<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Binding;

use Closure;
use InvalidArgumentException;
use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Input\KeyEvent;
use ReflectionFunction;

/**
 * Immutable value object describing a key combo → action mapping.
 *
 * Construction goes through the static factories; action and label are set
 * via fluent mutators that return a cloned instance. Binding is sealed after
 * the action setter is called — calling an action setter twice is an error.
 */
final class Binding
{
    private(set) Key|string $key;
    private(set) bool $ctrl = false;
    private(set) bool $alt = false;
    private(set) bool $shift = false;
    private(set) ?BindingAction $action = null;
    private(set) ?string $label = null;

    private function __construct(Key|string $key, bool $ctrl, bool $alt, bool $shift)
    {
        $this->key = $key;
        $this->ctrl = $ctrl;
        $this->alt = $alt;
        $this->shift = $shift;
    }

    // -------------------------------------------------------------------------
    // Static factories

    /** Ctrl + single character key, e.g. Binding::ctrl('c')->quit() */
    public static function ctrl(string $key): self
    {
        return new self($key, ctrl: true, alt: false, shift: false);
    }

    /** Alt + single character key, e.g. Binding::alt('x')->quit() */
    public static function alt(string $key): self
    {
        return new self($key, ctrl: false, alt: true, shift: false);
    }

    /** Plain key (named or character), e.g. Binding::key(Key::F12)->toggle(...) */
    public static function key(Key|string $key): self
    {
        return new self($key, ctrl: false, alt: false, shift: false);
    }

    // -------------------------------------------------------------------------
    // Action setters (fluent; clone so callers can reuse a base binding)

    public function quit(): self
    {
        return $this->withAction(BindingAction::quit());
    }

    /**
     * @param class-string $screen
     */
    public function workspace(string $screen): self
    {
        return $this->withAction(BindingAction::workspace($screen));
    }

    /**
     * @param class-string $component
     */
    public function toggle(string $component): self
    {
        return $this->withAction(BindingAction::toggle($component));
    }

    /**
     * Action closure MUST be declared static — a non-static closure can capture
     * object state, which causes lifecycle issues in long-running coroutine scope.
     */
    public function action(Closure $callback): self
    {
        $rf = new ReflectionFunction($callback);

        if (!$rf->isStatic()) {
            throw new InvalidArgumentException(
                'Binding action closures must be declared static to prevent object capture.',
            );
        }

        return $this->withAction(BindingAction::action($callback));
    }

    // -------------------------------------------------------------------------
    // Metadata

    public function label(string $label): self
    {
        $clone = clone $this;
        $clone->label = $label;

        return $clone;
    }

    // -------------------------------------------------------------------------
    // Matching

    public function matches(KeyEvent $event): bool
    {
        if ($event->key !== $this->key) {
            return false;
        }

        if ($event->ctrl !== $this->ctrl) {
            return false;
        }

        if ($event->alt !== $this->alt) {
            return false;
        }

        if ($event->shift !== $this->shift) {
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Internals

    private function withAction(BindingAction $action): self
    {
        $clone = clone $this;
        $clone->action = $action;

        return $clone;
    }
}
