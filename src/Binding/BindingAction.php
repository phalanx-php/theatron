<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Binding;

use Closure;

/**
 * Discriminated union of the four things a binding can do when triggered.
 * Each variant carries exactly the data its execution path needs.
 */
final class BindingAction
{
    // Variant discriminant.
    private(set) BindingActionKind $kind;

    // Payload fields — only one is populated per variant.
    private(set) ?string $target = null;
    private(set) ?Closure $callback = null;

    private function __construct(BindingActionKind $kind)
    {
        $this->kind = $kind;
    }

    public static function quit(): self
    {
        return new self(BindingActionKind::Quit);
    }

    /** @param class-string $screen */
    public static function workspace(string $screen): self
    {
        $action = new self(BindingActionKind::Workspace);
        $action->target = $screen;

        return $action;
    }

    /** @param class-string $component */
    public static function toggle(string $component): self
    {
        $action = new self(BindingActionKind::Toggle);
        $action->target = $component;

        return $action;
    }

    public static function action(Closure $callback): self
    {
        $instance = new self(BindingActionKind::Action);
        $instance->callback = $callback;

        return $instance;
    }

    public function isQuit(): bool
    {
        return $this->kind === BindingActionKind::Quit;
    }

    public function isWorkspace(): bool
    {
        return $this->kind === BindingActionKind::Workspace;
    }

    public function isToggle(): bool
    {
        return $this->kind === BindingActionKind::Toggle;
    }

    public function isAction(): bool
    {
        return $this->kind === BindingActionKind::Action;
    }
}
