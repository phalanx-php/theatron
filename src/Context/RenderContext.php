<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Context;

use Phalanx\Scope\Scope;
use Phalanx\Theatron\Component\MountedComponent;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Ui;

class RenderContext
{
    public function __construct(
        private(set) Scope $scope,
        private(set) Ui $ui,
        private(set) Theme $theme,
        private MountSystem $mountSystem,
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
}
