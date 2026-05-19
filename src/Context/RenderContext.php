<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Context;

use Phalanx\Scope\Scope;
use Phalanx\Theatron\Binding\BindingHintsFormatter;
use Phalanx\Theatron\Binding\BindingRegistry;
use Phalanx\Theatron\Component\MountedComponent;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;

class RenderContext
{
    public function __construct(
        private(set) Scope $scope,
        private(set) Ui $ui,
        private(set) Theme $theme,
        private MountSystem $mountSystem,
        private ?BindingRegistry $bindings = null,
    ) {
    }

    /**
     * @template T of Component
     * @param class-string<T> $component
     */
    public function mount(string $component, mixed ...$params): MountedComponent
    {
        return $this->mountSystem->mount($component, ...$params);
    }

    /**
     * Returns a Renderable row of key-hint chips for all currently active
     * bindings. When no BindingRegistry is wired, returns an empty row.
     *
     * Typical usage in a component's render method:
     *
     *   return $ctx->ui->column($body, $ctx->hints());
     */
    public function hints(): Renderable
    {
        if ($this->bindings === null) {
            return $this->ui->row();
        }

        return BindingHintsFormatter::render($this->ui, $this->bindings->activeBindings());
    }
}
