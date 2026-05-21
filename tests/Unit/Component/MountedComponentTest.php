<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Component;

use Phalanx\Theatron\Component\MountedComponent;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Component\SignalScanner;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Contract\Disposable as TheatronDisposable;
use Phalanx\Theatron\Reactive\DirtyBatch;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Reactive\Tracker;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MountedComponentTest extends TestCase
{
    #[Test]
    public function implementsRenderable(): void
    {
        $mounted = $this->createMounted(new class () implements Component {
            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('hello');
            }
        });

        self::assertInstanceOf(Renderable::class, $mounted);
    }

    #[Test]
    public function styleReturnsNull(): void
    {
        $mounted = $this->createMounted(new class () implements Component {
            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('test');
            }
        });

        self::assertNull($mounted->style);
    }

    #[Test]
    public function startsAsDirty(): void
    {
        $mounted = $this->createMounted(new class () implements Component {
            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('test');
            }
        });

        self::assertTrue($mounted->isDirty);
    }

    #[Test]
    public function renderClearsDirtyFlag(): void
    {
        $mounted = $this->createMounted(new class () implements Component {
            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('test');
            }
        });

        $mounted->render($this->createRenderCtx());

        self::assertFalse($mounted->isDirty);
    }

    #[Test]
    public function renderStoresLastResult(): void
    {
        $mounted = $this->createMounted(new class () implements Component {
            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('rendered');
            }
        });

        self::assertNull($mounted->lastResult());

        $result = $mounted->render($this->createRenderCtx());

        self::assertSame($result, $mounted->lastResult());
        self::assertInstanceOf(TextElement::class, $result);
    }

    #[Test]
    public function renderRunsInsideTrackerFrame(): void
    {
        $trackedDeps = null;

        $signal = new Signal('hello');
        $component = new class ($signal) implements Component {
            public function __construct(private(set) Signal $data)
            {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                $val = $this->data->get();

                return $ctx->ui->text((string) $val);
            }
        };

        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch, ['data' => $signal]);
        $mounted = new MountedComponent($component, $batch, $scanResult);

        $frame = Tracker::push();
        $mounted->render($this->createRenderCtx());
        $deps = Tracker::pop($frame);

        self::assertEmpty($deps, 'Component render uses its own Tracker frame, not the parent');
    }

    #[Test]
    public function signalWriteMarksDirty(): void
    {
        $component = new class () implements Component {
            public function __construct(
                private(set) Signal $count = new Signal(0),
            ) {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text((string) $this->count->get());
            }
        };

        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch);
        $mounted = new MountedComponent($component, $batch, $scanResult);

        $mounted->render($this->createRenderCtx());
        self::assertFalse($mounted->isDirty);

        $component->count->set(42);

        self::assertTrue($mounted->isDirty);
    }

    #[Test]
    public function rerenderUsesStoredContext(): void
    {
        $tracker = new \stdClass();
        $tracker->renderCount = 0;

        $component = new class ($tracker) implements Component {
            public function __construct(private \stdClass $tracker)
            {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                $this->tracker->renderCount++;

                return $ctx->ui->text('render ' . $this->tracker->renderCount);
            }
        };

        $mounted = $this->createMounted($component);
        $mounted->render($this->createRenderCtx());
        self::assertSame(1, $tracker->renderCount);

        $mounted->markDirty();
        $mounted->rerender();
        self::assertSame(2, $tracker->renderCount);
    }

    #[Test]
    public function disposeCleanupOwnedSignals(): void
    {
        $component = new class () implements Component {
            public function __construct(
                private(set) Signal $count = new Signal(0),
                private(set) Signal $name = new Signal(''),
            ) {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('test');
            }
        };

        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch);
        $mounted = new MountedComponent($component, $batch, $scanResult);

        self::assertFalse($component->count->isDisposed);

        $mounted->dispose();

        self::assertTrue($component->count->isDisposed);
        self::assertTrue($component->name->isDisposed);
        self::assertTrue($mounted->isDisposed);
    }

    #[Test]
    public function borrowedSignalsSurviveDispose(): void
    {
        $shared = new Signal('parent-owned');

        $component = new class ($shared) implements Component {
            public function __construct(
                private(set) Signal $input,
                private(set) Signal $local = new Signal(0),
            ) {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text((string) $this->input->get());
            }
        };

        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch, ['input' => $shared]);
        $mounted = new MountedComponent($component, $batch, $scanResult);

        $mounted->dispose();

        self::assertFalse($shared->isDisposed, 'Borrowed signal must survive child dispose');
        self::assertTrue($component->local->isDisposed, 'Owned signal must be disposed');
    }

    #[Test]
    public function disposeCallsComponentDisposable(): void
    {
        $tracker = new \stdClass();
        $tracker->disposed = false;

        $component = new class ($tracker) implements Component, TheatronDisposable {
            public function __construct(private \stdClass $tracker)
            {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('test');
            }

            public function dispose(): void
            {
                $this->tracker->disposed = true;
            }
        };

        $mounted = $this->createMounted($component);
        $mounted->dispose();

        self::assertTrue($tracker->disposed);
    }

    #[Test]
    public function disposeIsIdempotent(): void
    {
        $tracker = new \stdClass();
        $tracker->count = 0;

        $component = new class ($tracker) implements Component, TheatronDisposable {
            public function __construct(private \stdClass $tracker)
            {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('test');
            }

            public function dispose(): void
            {
                $this->tracker->count++;
            }
        };

        $mounted = $this->createMounted($component);
        $mounted->dispose();
        $mounted->dispose();

        self::assertSame(1, $tracker->count);
    }

    #[Test]
    public function rerenderAfterDisposeIsNoOp(): void
    {
        $tracker = new \stdClass();
        $tracker->renderCount = 0;

        $component = new class ($tracker) implements Component {
            public function __construct(private \stdClass $tracker)
            {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                $this->tracker->renderCount++;

                return $ctx->ui->text('test');
            }
        };

        $mounted = $this->createMounted($component);
        $mounted->render($this->createRenderCtx());
        self::assertSame(1, $tracker->renderCount);

        $mounted->dispose();
        $mounted->rerender();

        self::assertSame(1, $tracker->renderCount);
    }

    #[Test]
    public function renderAfterDisposeReturnsFallback(): void
    {
        $tracker = new \stdClass();
        $tracker->renderCount = 0;

        $component = new class ($tracker) implements Component {
            public function __construct(private \stdClass $tracker)
            {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                $this->tracker->renderCount++;

                return $ctx->ui->text('test');
            }
        };

        $mounted = $this->createMounted($component);
        $ctx = $this->createRenderCtx();
        $mounted->render($ctx);
        self::assertSame(1, $tracker->renderCount);

        $mounted->dispose();

        $result = $mounted->render($ctx);
        self::assertSame(1, $tracker->renderCount, 'Component __invoke must not run after dispose');
        self::assertInstanceOf(TextElement::class, $result);
    }

    #[Test]
    public function exceptionDuringRenderLeavesTrackerClean(): void
    {
        $component = new class () implements Component {
            public function __invoke(RenderContext $ctx): Renderable
            {
                throw new \RuntimeException('boom');
            }
        };

        $mounted = $this->createMounted($component);

        $frame = Tracker::push();

        try {
            $mounted->render($this->createRenderCtx());
        } catch (\RuntimeException) {
        }

        $deps = Tracker::pop($frame);
        self::assertEmpty($deps, 'Tracker frame must be clean after exception in component render');
    }

    #[Test]
    public function signalCountReflectsOwnedSignals(): void
    {
        $component = new class () implements Component {
            public function __construct(
                private(set) Signal $alpha = new Signal(0),
                private(set) Signal $beta = new Signal(''),
            ) {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('test');
            }
        };

        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch);
        $mounted = new MountedComponent($component, $batch, $scanResult);

        self::assertSame(2, $mounted->signalCount);
    }

    #[Test]
    public function subscriptionCountReflectsSubscriptions(): void
    {
        $signal = new Signal(0);

        $component = new class ($signal) implements Component {
            public function __construct(
                private(set) Signal $external,
                private(set) Signal $local = new Signal(''),
            ) {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text((string) $this->external->get());
            }
        };

        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch, ['external' => $signal]);
        $mounted = new MountedComponent($component, $batch, $scanResult);

        self::assertGreaterThan(0, $mounted->subscriptionCount);
    }

    #[Test]
    public function countsReturnZeroAfterDispose(): void
    {
        $component = new class () implements Component {
            public function __construct(
                private(set) Signal $alpha = new Signal(0),
                private(set) Signal $beta = new Signal(''),
            ) {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('test');
            }
        };

        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch);
        $mounted = new MountedComponent($component, $batch, $scanResult);

        self::assertSame(2, $mounted->signalCount);

        $mounted->dispose();

        self::assertSame(0, $mounted->signalCount);
        self::assertSame(0, $mounted->subscriptionCount);
    }

    private function createMounted(Component $component): MountedComponent
    {
        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch);

        return new MountedComponent($component, $batch, $scanResult);
    }

    private function createRenderCtx(): RenderContext
    {
        $scope = $this->createStub(\Phalanx\Scope\Scope::class);

        return new RenderContext(
            $scope,
            new Ui(),
            Theme::default(),
            new MountSystem($scope),
        );
    }
}
