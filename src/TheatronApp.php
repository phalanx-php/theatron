<?php

declare(strict_types=1);

namespace Phalanx\Theatron;

use Phalanx\Cancellation\Cancelled;
use Phalanx\Scope\ExecutionScope;
use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Binding\BindingRegistry;
use Phalanx\Theatron\Buffer\Rect;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Input\InputEvent;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Navigation\WorkspaceNavigator;
use Phalanx\Theatron\Stage\Stage;
use Phalanx\Theatron\State\Store;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Painter\PaintContext;
use Phalanx\Theatron\Tdom\Painter\Painter;
use Phalanx\Theatron\Tdom\Ui;

final class TheatronApp
{
    /**
     * @param list<class-string<Screen>> $screens
     * @param list<Binding> $globalBindings
     * @param class-string<Store>|null $storeClass
     */
    public function __construct(
        private(set) Stage $stage,
        private(set) Theme $theme,
        private(set) array $screens,
        private(set) array $globalBindings,
        private(set) ?string $storeClass,
        private(set) bool $devtools,
    ) {
    }

    public function start(ExecutionScope $scope): void
    {
        $registry = new BindingRegistry();
        $registry->setGlobal($this->globalBindings);

        $mountSystem = new MountSystem($scope);
        $navigator = new WorkspaceNavigator($mountSystem, $this->screens[0]);
        $registry->activateScreen($this->screens[0]);

        $ui = new Ui($this->theme);
        $screenCtx = new ScreenContext($scope, $ui, $this->theme, $navigator, $mountSystem);

        $mainRegion = $this->stage->region(
            'main',
            Rect::of(0, 0, $this->stage->width(), $this->stage->height()),
        );

        $this->stage->onResize(static function (int $w, int $h) use ($mainRegion): void {
            $mainRegion->resize(Rect::of(0, 0, $w, $h));
        });

        $this->stage->onDraw(static function () use ($navigator, $mainRegion, $screenCtx): void {
            $workspace = $navigator->activeWorkspace();

            if (!$workspace->isDirty) {
                return;
            }

            $renderable = $workspace->render($screenCtx);
            $mainRegion->buffer()->clear();
            Painter::paint($renderable, new PaintContext($mainRegion->area, $mainRegion->buffer()));
            $mainRegion->markDirty();
        });

        $this->stage->onInput(static function (InputEvent $event) use ($registry, $navigator, $scope): void {
            if (!$event instanceof KeyEvent) {
                return;
            }

            $binding = $registry->resolve($event);

            if ($binding === null) {
                return;
            }

            $action = $binding->action;

            if ($action === null) {
                return;
            }

            if ($action->isQuit()) {
                $scope->cancellation()->cancel();

                return;
            }

            if ($action->isWorkspace()) {
                /** @var class-string<Screen> $target */
                $target = $action->target;
                $navigator->go($target);
                $registry->activateScreen($target);

                return;
            }

            if ($action->isAction() && $action->callback !== null) {
                ($action->callback)();

                return;
            }

            if ($action->isToggle() && $action->target !== null) {
                /** @var class-string<\Phalanx\Theatron\Contract\Component> $target */
                $target = $action->target;

                if ($navigator->hasOverlays()) {
                    $navigator->dismiss();
                } else {
                    $navigator->overlay($target);
                }
            }
        });

        $this->stage->start($scope);

        try {
            while (!$scope->isCancelled) {
                $scope->delay(0.1);
            }
        } catch (Cancelled $e) {
            if (!$scope->isCancelled) {
                throw $e;
            }
        }
    }

    /** @return list<class-string<Screen>> */
    public function screens(): array
    {
        return $this->screens;
    }

    /** @return list<Binding> */
    public function globalBindings(): array
    {
        return $this->globalBindings;
    }
}
