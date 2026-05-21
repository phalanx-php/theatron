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
use Phalanx\Theatron\Reactive\DirtyBatch;
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
