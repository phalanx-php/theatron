<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Component;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountedScreen;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Component\SignalScanner;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Reactive\Computed;
use Phalanx\Theatron\Reactive\DirtyBatch;
use Phalanx\Theatron\Reactive\Resource;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MountedScreenTest extends TestCase
{
    #[Test]
    public function renderTimeConditionalDependenciesAreReconciled(): void
    {
        $model = new \stdClass();
        $model->useA = new Signal(true);
        $model->a = new Signal('a');
        $model->b = new Signal('b');

        $screen = new class ($model) implements Screen {
            public function __construct(private \stdClass $model)
            {
            }

            public function __invoke(ScreenContext $ctx): Renderable
            {
                $value = $this->model->useA->get()
                    ? $this->model->a->get()
                    : $this->model->b->get();

                return $ctx->ui->text((string) $value);
            }
        };

        $mounted = $this->createMounted($screen);
        $ctx = $this->createScreenCtx();

        $mounted->render($ctx);
        self::assertFalse($mounted->isDirty);

        $model->useA->set(false);
        self::assertTrue($mounted->isDirty);

        $mounted->render($ctx);
        self::assertFalse($mounted->isDirty);

        $model->a->set('old branch');
        self::assertFalse($mounted->isDirty);

        $model->b->set('new branch');
        self::assertTrue($mounted->isDirty);
    }

    #[Test]
    public function failedRenderLeavesScreenDirtyAndPreservesPreviousDependencies(): void
    {
        $model = new \stdClass();
        $model->throw = false;
        $model->good = new Signal('good');
        $model->bad = new Signal('bad');

        $screen = new class ($model) implements Screen {
            public function __construct(private \stdClass $model)
            {
            }

            public function __invoke(ScreenContext $ctx): Renderable
            {
                if ($this->model->throw) {
                    $this->model->bad->get();

                    throw new \RuntimeException('screen failed');
                }

                return $ctx->ui->text((string) $this->model->good->get());
            }
        };

        $mounted = $this->createMounted($screen);
        $ctx = $this->createScreenCtx();
        $mounted->render($ctx);
        self::assertFalse($mounted->isDirty);

        $model->throw = true;

        try {
            $mounted->render($ctx);
            self::fail('Expected failed screen render.');
        } catch (\RuntimeException $e) {
            self::assertSame('screen failed', $e->getMessage());
        }

        self::assertTrue($mounted->isDirty);
        $model->throw = false;

        $mounted->render($ctx);
        self::assertFalse($mounted->isDirty);

        $model->bad->set('bad changed');
        self::assertFalse($mounted->isDirty);

        $model->good->set('good changed');
        self::assertTrue($mounted->isDirty);
    }

    #[Test]
    public function renderTimeResourceAndComputedDependenciesMarkScreenDirty(): void
    {
        $model = new \stdClass();
        $model->source = new Signal('first');
        $model->label = new Computed(static fn(): string => strtoupper((string) $model->source->get()));
        $model->reply = new Resource(static fn(): iterable => ['streamed']);

        $screen = new class ($model) implements Screen {
            public function __construct(private \stdClass $model)
            {
            }

            public function __invoke(ScreenContext $ctx): Renderable
            {
                return $ctx->ui->text($this->model->label->value . ':' . $this->model->reply->buffer);
            }
        };

        $mounted = $this->createMounted($screen);
        $ctx = $this->createScreenCtx();
        $mounted->render($ctx);
        self::assertFalse($mounted->isDirty);

        $model->reply->stream();
        self::assertTrue($mounted->isDirty);

        $mounted->render($ctx);
        self::assertFalse($mounted->isDirty);

        $model->source->set('second');
        self::assertTrue($mounted->isDirty);
    }

    #[Test]
    public function disposeCleansScreenRenderTimeDependencies(): void
    {
        $model = new \stdClass();
        $model->signal = new Signal('active');

        $screen = new class ($model) implements Screen {
            public function __construct(private \stdClass $model)
            {
            }

            public function __invoke(ScreenContext $ctx): Renderable
            {
                return $ctx->ui->text((string) $this->model->signal->get());
            }
        };

        $mounted = $this->createMounted($screen);
        $mounted->render($this->createScreenCtx());
        self::assertSame(1, $model->signal->subscriberCount);

        $mounted->dispose();

        self::assertSame(0, $model->signal->subscriberCount);
    }

    #[Test]
    public function failedReconcileLeavesPreviousScreenRenderDependenciesSubscribed(): void
    {
        $model = new \stdClass();
        $model->useBad = new Signal(false);
        $model->good = new Signal('good');
        $model->bad = new Signal('bad');
        $model->bad->dispose();

        $screen = new class ($model) implements Screen {
            public function __construct(private \stdClass $model)
            {
            }

            public function __invoke(ScreenContext $ctx): Renderable
            {
                $value = $this->model->useBad->get()
                    ? $this->model->bad->get()
                    : $this->model->good->get();

                return $ctx->ui->text((string) $value);
            }
        };

        $mounted = $this->createMounted($screen);
        $ctx = $this->createScreenCtx();
        $mounted->render($ctx);
        self::assertSame(1, $model->good->subscriberCount);

        $model->useBad->set(true);

        try {
            $mounted->render($ctx);
            self::fail('Expected disposed render dependency subscription failure.');
        } catch (\RuntimeException $e) {
            self::assertSame('Cannot subscribe to a disposed signal.', $e->getMessage());
        }

        self::assertTrue($mounted->isDirty);
        self::assertSame(1, $model->good->subscriberCount);
    }

    private function createMounted(Screen $screen): MountedScreen
    {
        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($screen, $batch);

        return new MountedScreen($screen, $batch, $scanResult);
    }

    private function createScreenCtx(): ScreenContext
    {
        $scope = $this->createStub(TaskScope::class);

        return new ScreenContext(
            $scope,
            new Ui(),
            Theme::default(),
            $this->createStub(Navigator::class),
            new MountSystem($scope),
        );
    }
}
