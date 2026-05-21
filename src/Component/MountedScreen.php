<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Component;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Disposable as TheatronDisposable;
use Phalanx\Theatron\Contract\Mountable;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Reactive\DirtyBatch;
use Phalanx\Theatron\Reactive\RenderDependencySet;
use Phalanx\Theatron\Reactive\ResourceSubscription;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Reactive\SignalSubscription;
use Phalanx\Theatron\Reactive\Tracker;
use Phalanx\Theatron\State\StoreSubscription;
use Phalanx\Theatron\Tdom\Renderable;
use Throwable;

final class MountedScreen
{
    private(set) bool $isDisposed = false;

    /** Dirty state is owned by the screen render dependency batch. */
    public bool $isDirty {
        get => $this->dirty->isDirty;
    }

    private ?Renderable $lastResult = null;
    private ?ScreenContext $renderCtx = null;

    /** @var list<Signal> */
    private array $ownedSignals;

    /** @var list<SignalSubscription|ResourceSubscription> */
    private array $subscriptions;

    /** @var list<StoreSubscription> */
    private array $storeSubscriptions;

    private RenderDependencySet $renderDependencies;
    private bool $mountLifecycleStarted = false;

    public function __construct(
        private(set) Screen $screen,
        private(set) DirtyBatch $dirty,
        SignalScanResult $scanResult,
    ) {
        $this->renderDependencies = new RenderDependencySet($this->dirty, $scanResult->renderIgnoredReactives);
        $this->ownedSignals = $scanResult->ownedSignals;
        $this->subscriptions = $scanResult->subscriptions;
        $this->storeSubscriptions = $scanResult->storeSubscriptions;
        $this->dirty->request();
    }

    public function activate(TaskScope $scope): void
    {
        if ($this->mountLifecycleStarted || !$this->screen instanceof Mountable) {
            return;
        }

        $this->screen->onMount($scope);
        $this->mountLifecycleStarted = true;
    }

    public function render(ScreenContext $ctx): Renderable
    {
        if ($this->isDisposed) {
            return $this->lastResult ?? $ctx->ui->text('');
        }

        $this->renderCtx = $ctx;
        $this->dirty->consume();

        $screen = $this->screen;
        $ctx->mountSystem->enterFrame($this);
        $commitMountFrame = false;
        try {
            $frame = Tracker::push();
            $popped = false;
            try {
                $result = $ctx->renderDiagnostics->screen(
                    $ctx->scope,
                    $screen,
                    static fn(): Renderable => $ctx->mountSystem->resolve(
                        $screen($ctx),
                    ),
                );
                $deps = Tracker::pop($frame);
                $popped = true;
            } catch (Throwable $e) {
                $this->dirty->request();

                throw $e;
            } finally {
                if (!$popped) {
                    Tracker::pop($frame);
                }
            }

            try {
                $this->renderDependencies->reconcile($deps);
            } catch (Throwable $e) {
                $this->dirty->request();

                throw $e;
            }

            $this->lastResult = $result;
            $commitMountFrame = true;

            return $result;
        } finally {
            $ctx->mountSystem->leaveFrame($this, $commitMountFrame);
        }
    }

    public function rerender(): void
    {
        if ($this->renderCtx !== null && !$this->isDisposed) {
            $this->render($this->renderCtx);
        }
    }

    public function lastResult(): ?Renderable
    {
        return $this->lastResult;
    }

    public function markDirty(): void
    {
        $this->dirty->request();
    }

    public function dispose(): void
    {
        if ($this->isDisposed) {
            return;
        }

        $this->isDisposed = true;

        $this->renderCtx?->mountSystem->disposeOwnedSlots($this);

        foreach ($this->subscriptions as $sub) {
            $sub->dispose();
        }
        $this->subscriptions = [];

        foreach ($this->storeSubscriptions as $sub) {
            $sub->dispose();
        }
        $this->storeSubscriptions = [];

        $this->renderDependencies->dispose();

        foreach ($this->ownedSignals as $signal) {
            $signal->dispose();
        }
        $this->ownedSignals = [];

        if ($this->mountLifecycleStarted && $this->screen instanceof Mountable) {
            $this->screen->onUnmount();
            $this->mountLifecycleStarted = false;
        }

        if ($this->screen instanceof TheatronDisposable) {
            $this->screen->dispose();
        }

        $this->lastResult = null;
        $this->renderCtx = null;
    }
}
