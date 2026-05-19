<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Component;

use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Contract\Disposable as TheatronDisposable;
use Phalanx\Theatron\Contract\Mountable;
use Phalanx\Theatron\Reactive\DirtyBatch;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Reactive\SignalSubscription;
use Phalanx\Theatron\Reactive\Tracker;
use Phalanx\Theatron\State\StoreSubscription;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style;

final class MountedComponent implements Renderable
{
    private(set) bool $isDisposed = false;
    public ?Style $style {
        get => null;
    }

    public bool $isDirty {
        get => $this->dirty->isDirty;
    }

    private ?Renderable $lastResult = null;
    private ?RenderContext $renderCtx = null;

    /** @var list<Signal> */
    private array $ownedSignals;

    /** @var list<SignalSubscription> */
    private array $subscriptions;

    /** @var list<StoreSubscription> */
    private array $storeSubscriptions;

    public function __construct(
        private(set) Component $component,
        private(set) DirtyBatch $dirty,
        SignalScanResult $scanResult,
    ) {
        $this->ownedSignals = $scanResult->ownedSignals;
        $this->subscriptions = $scanResult->subscriptions;
        $this->storeSubscriptions = $scanResult->storeSubscriptions;
        $this->dirty->request();
    }

    public function render(RenderContext $ctx): Renderable
    {
        if ($this->isDisposed) {
            return $this->lastResult ?? $ctx->ui->text('');
        }

        $this->renderCtx = $ctx;
        $this->dirty->consume();

        $frame = Tracker::push();
        try {
            $result = ($this->component)($ctx);
        } finally {
            Tracker::pop($frame);
        }

        $this->lastResult = $result;

        return $result;
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

        foreach ($this->subscriptions as $sub) {
            $sub->dispose();
        }
        $this->subscriptions = [];

        foreach ($this->storeSubscriptions as $sub) {
            $sub->dispose();
        }
        $this->storeSubscriptions = [];

        foreach ($this->ownedSignals as $signal) {
            $signal->dispose();
        }
        $this->ownedSignals = [];

        if ($this->component instanceof Mountable) {
            $this->component->onUnmount();
        }

        if ($this->component instanceof TheatronDisposable) {
            $this->component->dispose();
        }

        $this->lastResult = null;
        $this->renderCtx = null;
    }
}
