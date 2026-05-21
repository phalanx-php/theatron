<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Component;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountedComponent;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Contract\Mountable;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Reactive\SignalRegistry;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MountSystemTest extends TestCase
{
    #[Test]
    public function mountReturnsMountedComponent(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $mounted = $system->mount(SimpleTestComponent::class);

        self::assertInstanceOf(MountedComponent::class, $mounted);
    }

    #[Test]
    public function mountPassesRuntimeParams(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $mounted = $system->mount(ParamTestComponent::class, label: 'Apollo');

        $ctx = new RenderContext(
            $this->createStub(\Phalanx\Scope\Scope::class),
            new Ui(),
            Theme::default(),
            $system,
        );

        $result = $mounted->render($ctx);
        self::assertInstanceOf(TextElement::class, $result);
        self::assertSame('Apollo', $result->content);
    }

    #[Test]
    public function mountCreatesComponentWithSignalDefaults(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $mounted = $system->mount(SignalTestComponent::class);

        self::assertTrue($mounted->isDirty);
    }

    #[Test]
    public function mountPassesSharedSignal(): void
    {
        $shared = new Signal('from-parent');
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $mounted = $system->mount(BorrowingComponent::class, input: $shared);

        $ctx = new RenderContext(
            $this->createStub(\Phalanx\Scope\Scope::class),
            new Ui(),
            Theme::default(),
            $system,
        );

        $mounted->render($ctx);
        $mounted->dispose();

        self::assertFalse($shared->isDisposed, 'Shared signal survives child dispose');
    }

    #[Test]
    public function disposeAllCleansUpAllMounted(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $a = $system->mount(SimpleTestComponent::class);
        $b = $system->mount(SimpleTestComponent::class);

        self::assertFalse($a->isDisposed);
        self::assertFalse($b->isDisposed);

        $system->disposeAll();

        self::assertTrue($a->isDisposed);
        self::assertTrue($b->isDisposed);
    }

    #[Test]
    public function disposeAllIsIdempotent(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $mounted = $system->mount(SimpleTestComponent::class);

        $system->disposeAll();
        $system->disposeAll();

        self::assertTrue($mounted->isDisposed);
    }

    #[Test]
    public function mountCallsOnMountWithTaskScope(): void
    {
        $tracker = new \stdClass();
        $tracker->mounted = false;
        $tracker->scope = null;

        $taskScope = $this->createStub(TaskScope::class);
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class), $taskScope);

        $system->mount(MountableTestComponent::class, tracker: $tracker);

        self::assertTrue($tracker->mounted);
        self::assertSame($taskScope, $tracker->scope);
    }

    #[Test]
    public function mountSkipsOnMountWithoutTaskScope(): void
    {
        $tracker = new \stdClass();
        $tracker->mounted = false;
        $tracker->scope = null;
        $tracker->unmounted = false;

        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $system->mount(MountableTestComponent::class, tracker: $tracker);

        self::assertFalse($tracker->mounted, 'onMount must not be called without TaskScope');
    }

    #[Test]
    public function disposeCallsOnUnmount(): void
    {
        $tracker = new \stdClass();
        $tracker->mounted = false;
        $tracker->scope = null;
        $tracker->unmounted = false;

        $taskScope = $this->createStub(TaskScope::class);
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class), $taskScope);

        $mounted = $system->mount(MountableTestComponent::class, tracker: $tracker);

        self::assertTrue($tracker->mounted);
        self::assertFalse($tracker->unmounted);

        $mounted->dispose();

        self::assertTrue($tracker->unmounted, 'onUnmount must be called during dispose');
    }

    #[Test]
    public function mountAfterDisposeAllWorks(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $a = $system->mount(SimpleTestComponent::class);
        $system->disposeAll();
        self::assertTrue($a->isDisposed);

        $b = $system->mount(SimpleTestComponent::class);
        self::assertFalse($b->isDisposed);
    }

    #[Test]
    public function mountedReturnsAllMountedComponents(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        self::assertCount(0, $system->mounted());

        $system->mount(SimpleTestComponent::class);
        $system->mount(SimpleTestComponent::class);

        self::assertCount(2, $system->mounted());
    }

    #[Test]
    public function mountedReturnsCopyNotReference(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $system->mount(SimpleTestComponent::class);

        $copy = $system->mounted();
        $copy[] = $system->mount(SimpleTestComponent::class);

        self::assertCount(2, $system->mounted(), 'Mutating returned array must not affect internal list');
    }

    #[Test]
    public function disposeAllClearsMountedList(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $system->mount(SimpleTestComponent::class);
        $system->mount(SimpleTestComponent::class);

        self::assertCount(2, $system->mounted());

        $system->disposeAll();

        self::assertCount(0, $system->mounted());
    }

    #[Test]
    public function mountThreadsRegistryToScanner(): void
    {
        $registry = new SignalRegistry();
        $system = new MountSystem(
            $this->createStub(\Phalanx\Scope\Scope::class),
            registry: $registry,
        );

        $system->mount(SignalHoldingComponent::class);

        $snapshot = $registry->snapshot();
        self::assertCount(1, $snapshot);
        self::assertStringEndsWith('::counter', $snapshot[0]->label);
    }

    #[Test]
    public function mountWithoutRegistrySkipsRegistration(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));

        $mounted = $system->mount(SignalHoldingComponent::class);

        self::assertSame(1, $mounted->signalCount);
    }

    #[Test]
    public function borrowedSignalIsNotRegistered(): void
    {
        $registry = new SignalRegistry();
        $shared = new Signal('parent-owned');
        $system = new MountSystem(
            $this->createStub(\Phalanx\Scope\Scope::class),
            registry: $registry,
        );

        $system->mount(BorrowedOnlyComponent::class, input: $shared);

        self::assertSame(0, $registry->count());
    }

    #[Test]
    public function provideSuppliesAmbientDependency(): void
    {
        $system = new MountSystem($this->createStub(\Phalanx\Scope\Scope::class));
        $service = new ProvidedService('olympus');
        $system->provide(ProvidedService::class, $service);

        $mounted = $system->mount(ServiceConsumerComponent::class);

        $ctx = new RenderContext(
            $this->createStub(\Phalanx\Scope\Scope::class),
            new Ui(),
            Theme::default(),
            $system,
        );

        $result = $mounted->render($ctx);
        self::assertInstanceOf(TextElement::class, $result);
        self::assertSame('olympus', $result->content);
    }
}

final class SimpleTestComponent implements Component
{
    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text('simple');
    }
}

final class ParamTestComponent implements Component
{
    public function __construct(
        private(set) string $label = 'default',
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text($this->label);
    }
}

final class SignalTestComponent implements Component
{
    public function __construct(
        private(set) Signal $count = new Signal(0),
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text((string) $this->count->get());
    }
}

final class BorrowingComponent implements Component
{
    public function __construct(
        private(set) Signal $input,
        private(set) Signal $local = new Signal(''),
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text((string) $this->input->get());
    }
}

final class MountableTestComponent implements Component, Mountable
{
    public function __construct(
        private \stdClass $tracker,
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text('mountable');
    }

    public function onMount(TaskScope $scope): void
    {
        $this->tracker->mounted = true;
        $this->tracker->scope = $scope;
    }

    public function onUnmount(): void
    {
        $this->tracker->unmounted = true;
    }
}

final class ProvidedService
{
    public function __construct(
        private(set) string $name,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }
}

final class ServiceConsumerComponent implements Component
{
    public function __construct(
        private(set) ProvidedService $service,
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text($this->service->name());
    }
}

final class SignalHoldingComponent implements Component
{
    public function __construct(
        private(set) Signal $counter = new Signal(0),
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text((string) $this->counter->get());
    }
}

final class BorrowedOnlyComponent implements Component
{
    public function __construct(
        private(set) Signal $input,
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text((string) $this->input->get());
    }
}
