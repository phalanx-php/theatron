<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Component;

use Closure;
use Phalanx\Concurrency\RetryPolicy;
use Phalanx\Concurrency\SettlementBag;
use Phalanx\Runtime\Identity\AegisResourceSid;
use Phalanx\Scope\ExecutionScope;
use Phalanx\Scope\Scope;
use Phalanx\Scope\Subscription;
use Phalanx\Scope\TaskExecutor;
use Phalanx\Supervisor\TaskHandle;
use Phalanx\Task\Executable;
use Phalanx\Task\Scopeable;
use Phalanx\Testing\PhalanxTestCase;
use Phalanx\Theatron\Component\MountedComponent;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Component\SignalScanner;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Reactive\DirtyBatch;
use Phalanx\Theatron\Reactive\Resource;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Rendering\RenderDiagnostics;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Worker\WorkerTask;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

use function Phalanx\Theatron\Ui\column;
use function Phalanx\Theatron\Ui\mount;

final class MemoryDisposalChurnTest extends PhalanxTestCase
{
    #[Test]
    public function repeatedSlotReplacementDoesNotGrowMountedChildrenOrSubscriptions(): void
    {
        $system = new MountSystem($this->createStub(Scope::class));
        $model = new C6SlotChurnModel();
        $parent = $this->mountComponent(new C6SlotChurnParent($model));
        $ctx = $this->renderContext($system);
        $previous = null;

        for ($i = 0; $i < 25; $i++) {
            $model->label->set("label-{$i}");

            $parent->render($ctx);
            $mounted = $system->mounted();
            self::assertCount(1, $mounted);

            $child = $mounted[0];
            $child->render($ctx);

            if ($previous !== null) {
                self::assertTrue($previous->isDisposed);
            }

            self::assertFalse($child->isDisposed);
            self::assertSame(1, $model->childSignal->subscriberCount);

            $previous = $child;
        }

        $parent->dispose();

        self::assertSame(0, $model->label->subscriberCount);
        self::assertSame(0, $model->childSignal->subscriberCount);
        self::assertCount(0, $system->mounted());
    }

    #[Test]
    public function repeatedNestedSlotReplacementDisposesDescendants(): void
    {
        $system = new MountSystem($this->createStub(Scope::class));
        $model = new C6SlotChurnModel();
        $parent = $this->mountComponent(new C6NestedSlotChurnParent($model));
        $ctx = $this->renderContext($system);
        $previousPair = null;
        $activeSubscriberCount = null;

        for ($i = 0; $i < 20; $i++) {
            $model->label->set("nested-{$i}");

            $parent->render($ctx);
            $nested = $system->mounted()[0];
            $nested->render($ctx);
            $descendant = $system->mounted()[1];
            $descendant->render($ctx);

            if ($previousPair !== null) {
                self::assertTrue($previousPair[0]->isDisposed);
                self::assertTrue($previousPair[1]->isDisposed);
            }

            $activeSubscriberCount ??= $model->childSignal->subscriberCount;

            self::assertCount(2, $system->mounted());
            self::assertSame($activeSubscriberCount, $model->childSignal->subscriberCount);

            $previousPair = [$nested, $descendant];
        }

        $parent->dispose();

        self::assertSame(0, $model->label->subscriberCount);
        self::assertSame(0, $model->childSignal->subscriberCount);
        self::assertCount(0, $system->mounted());
    }

    #[Test]
    public function resourceStreamChurnCancelsSupersededTasksAndRejectsDisposedWrites(): void
    {
        $dirty = 0;
        $executor = new C6QueuedTaskExecutor();

        $resource = new Resource(
            fetcher: static fn(int $key): iterable => ["chunk-{$key}"],
            executor: $executor,
            onDirty: static function () use (&$dirty): void {
                $dirty++;
            },
        );
        $subscription = $resource->subscribe(static function () use (&$dirty): void {
            $dirty++;
        });

        for ($i = 0; $i < 30; $i++) {
            $resource->stream($i);
        }

        self::assertSame(30, $executor->queuedCount());
        self::assertSame(29, $executor->cancelCount());
        self::assertSame(1, $resource->subscriberCount);

        $executor->runLastQueued();

        self::assertTrue($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('chunk-29', $resource->buffer);
        self::assertSame('chunk-29', $resource->value);

        $buffer = $resource->buffer;
        $value = $resource->value;

        $resource->dispose();
        $resource->stream(999);
        $executor->runAllQueued();

        $subscription->dispose();

        self::assertTrue($subscription->isDisposed);
        self::assertSame(0, $resource->subscriberCount);
        self::assertSame(29, $executor->cancelCount());
        self::assertSame($buffer, $resource->buffer);
        self::assertSame($value, $resource->value);
        self::assertGreaterThan(0, $dirty);
    }

    #[Test]
    public function diagnosticRenderTaskChurnLeavesNoLiveAegisTaskRuns(): void
    {
        $diagnostics = RenderDiagnostics::enabled();
        $target = new C6DiagnosticRenderTarget();

        self::assertSame(0, $this->scope->memory->resources->liveCount(AegisResourceSid::TaskRun));

        $this->scope->run(static function (ExecutionScope $scope) use ($diagnostics, $target): void {
            for ($i = 0; $i < 50; $i++) {
                $result = $diagnostics->component(
                    $scope,
                    $target,
                    static fn(): string => 'rendered',
                );

                self::assertSame('rendered', $result);
            }
        });

        self::assertSame(0, $this->scope->memory->resources->liveCount(AegisResourceSid::TaskRun));
    }

    private function mountComponent(Component $component): MountedComponent
    {
        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch);

        return new MountedComponent($component, $batch, $scanResult);
    }

    private function renderContext(MountSystem $system): RenderContext
    {
        return new RenderContext(
            $this->createStub(Scope::class),
            new Ui(),
            Theme::default(),
            $system,
        );
    }
}

final class C6SlotChurnModel
{
    public Signal $label;

    public Signal $childSignal;

    public function __construct()
    {
        $this->label = new Signal('initial');
        $this->childSignal = new Signal('child');
    }
}

final class C6SlotChurnParent implements Component
{
    public function __construct(
        private C6SlotChurnModel $model,
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return column(
            mount(
                C6SlotChurnChild::class,
                label: (string) $this->model->label->get(),
                signal: $this->model->childSignal,
            ),
        );
    }
}

final class C6NestedSlotChurnParent implements Component
{
    public function __construct(
        private C6SlotChurnModel $model,
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return column(
            mount(
                C6NestedSlotChurnChildOwner::class,
                label: (string) $this->model->label->get(),
                signal: $this->model->childSignal,
            ),
        );
    }
}

final class C6NestedSlotChurnChildOwner implements Component
{
    public function __construct(
        private(set) string $label,
        private(set) Signal $signal,
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return column(
            mount(
                C6SlotChurnChild::class,
                label: $this->label . '-child',
                signal: $this->signal,
            ),
        );
    }
}

final class C6SlotChurnChild implements Component
{
    public function __construct(
        private(set) string $label,
        private(set) Signal $signal,
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text($this->label . ':' . (string) $this->signal->get());
    }
}

final class C6DiagnosticRenderTarget
{
}

final class C6QueuedTaskExecutor implements TaskExecutor
{
    /** @var list<array{id:int,task:Closure,cancelled:bool}> */
    private array $tasks = [];

    private int $nextId = 0;

    private int $cancelCount = 0;

    public function queuedCount(): int
    {
        return count($this->tasks);
    }

    public function cancelCount(): int
    {
        return $this->cancelCount;
    }

    public function runLastQueued(): mixed
    {
        if ($this->tasks === []) {
            throw new RuntimeException('No queued tasks remain.');
        }

        return $this->runQueued(array_key_last($this->tasks));
    }

    public function runAllQueued(): void
    {
        while ($this->tasks !== []) {
            $this->runQueued(array_key_first($this->tasks));
        }
    }

    public function go(Closure $fn, ?string $name = null): TaskHandle
    {
        $id = $this->nextId++;
        $this->tasks[] = [
            'id' => $id,
            'task' => $fn,
            'cancelled' => false,
        ];

        $tasks = &$this->tasks;
        $cancelCount = &$this->cancelCount;

        return new TaskHandle(
            id: "c6-task-{$id}",
            name: $name ?? 'c6-task',
            cancel: static function () use (&$tasks, &$cancelCount, $id): void {
                foreach ($tasks as &$task) {
                    if ($task['id'] !== $id || $task['cancelled']) {
                        continue;
                    }

                    $task['cancelled'] = true;
                    $cancelCount++;

                    return;
                }
            },
            snapshot: static fn(): null => null,
        );
    }

    /** @return array<string|int, mixed> */
    public function concurrent(Scopeable|Executable|Closure ...$tasks): array
    {
        throw new RuntimeException('concurrent is not implemented by the C6 queued executor.');
    }

    public function race(Scopeable|Executable|Closure ...$tasks): mixed
    {
        throw new RuntimeException('race is not implemented by the C6 queued executor.');
    }

    public function any(Scopeable|Executable|Closure ...$tasks): mixed
    {
        throw new RuntimeException('any is not implemented by the C6 queued executor.');
    }

    public function map(iterable $items, Closure $fn, int $limit = 10, ?Closure $onEach = null): array
    {
        throw new RuntimeException('map is not implemented by the C6 queued executor.');
    }

    /** @return array<string|int, mixed> */
    public function series(Scopeable|Executable|Closure ...$tasks): array
    {
        throw new RuntimeException('series is not implemented by the C6 queued executor.');
    }

    public function waterfall(Scopeable|Executable|Closure ...$tasks): mixed
    {
        throw new RuntimeException('waterfall is not implemented by the C6 queued executor.');
    }

    public function settle(Scopeable|Executable|Closure ...$tasks): SettlementBag
    {
        throw new RuntimeException('settle is not implemented by the C6 queued executor.');
    }

    public function timeout(float $seconds, Scopeable|Executable|Closure $task): mixed
    {
        throw new RuntimeException('timeout is not implemented by the C6 queued executor.');
    }

    public function retry(Scopeable|Executable|Closure $task, RetryPolicy $policy): mixed
    {
        throw new RuntimeException('retry is not implemented by the C6 queued executor.');
    }

    public function delay(float $seconds): void
    {
        throw new RuntimeException('delay is not implemented by the C6 queued executor.');
    }

    public function periodic(float $interval, Closure $tick): Subscription
    {
        throw new RuntimeException('periodic is not implemented by the C6 queued executor.');
    }

    public function defer(Scopeable|Executable|Closure $task): void
    {
        throw new RuntimeException('defer is not implemented by the C6 queued executor.');
    }

    public function singleflight(string $key, Scopeable|Executable|Closure $task): mixed
    {
        throw new RuntimeException('singleflight is not implemented by the C6 queued executor.');
    }

    public function inWorker(WorkerTask $task): mixed
    {
        throw new RuntimeException('inWorker is not implemented by the C6 queued executor.');
    }

    /** @return array<string|int, mixed> */
    public function parallel(WorkerTask ...$tasks): array
    {
        throw new RuntimeException('parallel is not implemented by the C6 queued executor.');
    }

    public function settleParallel(WorkerTask ...$tasks): SettlementBag
    {
        throw new RuntimeException('settleParallel is not implemented by the C6 queued executor.');
    }

    /** @return array<string|int, mixed> */
    public function mapParallel(iterable $items, Closure $fn, int $limit = 10, ?Closure $onEach = null): array
    {
        throw new RuntimeException('mapParallel is not implemented by the C6 queued executor.');
    }

    private function runQueued(int $index): mixed
    {
        $entry = $this->tasks[$index] ?? throw new RuntimeException("No queued task at index {$index}.");

        try {
            return $entry['cancelled'] ? null : $entry['task']();
        } finally {
            array_splice($this->tasks, $index, 1);
        }
    }
}
