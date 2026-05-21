<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Context;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountedComponent;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Rendering\RenderDiagnostics;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Ui;

class ScreenContext
{
    private(set) RenderDiagnostics $renderDiagnostics;

    public function __construct(
        private(set) TaskScope $scope,
        private(set) Ui $ui,
        private(set) Theme $theme,
        private(set) Navigator $navigator,
        private(set) MountSystem $mountSystem,
        ?RenderDiagnostics $renderDiagnostics = null,
    ) {
        $this->renderDiagnostics = $renderDiagnostics ?? new RenderDiagnostics();
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
