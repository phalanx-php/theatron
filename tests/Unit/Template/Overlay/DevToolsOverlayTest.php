<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template\Overlay;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Layout\Border;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Reactive\SignalRegistry;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\RowElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Overlay\DevToolsOverlay;
use Phalanx\Theatron\Template\Overlay\DevToolsTab;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\AgentRegistrySlice;
use Phalanx\Theatron\Template\Slice\AgentSummary;
use Phalanx\Theatron\Template\Slice\ConversationSlice;
use Phalanx\Theatron\Text\Line;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DevToolsOverlayTest extends TestCase
{
    #[Test]
    public function rendersColumnWithTabBarAndBody(): void
    {
        [$overlay] = $this->makeOverlay();
        $root      = $this->assertColumn($overlay($this->makeContext()));

        self::assertCount(2, $root->children);
        self::assertInstanceOf(TextElement::class, $root->children[0]);

        $body = $this->assertPanel($root->children[1]);
        self::assertSame('', $body->title);

        $bodyStyle = $body->style;
        self::assertNotNull($bodyStyle);
        self::assertSame(Border::Rounded, $bodyStyle->border);
    }

    #[Test]
    public function defaultTabIsMetrics(): void
    {
        [$overlay] = $this->makeOverlay();

        self::assertSame(DevToolsTab::Metrics->value, $overlay->activeTab->get());
    }

    #[Test]
    public function renderMetricsTabShowsMemory(): void
    {
        [$overlay, $mountSystem] = $this->makeOverlay();
        $root                   = $this->assertColumn($overlay($this->makeContext($mountSystem)));

        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('mem/r:', $flat);
        self::assertStringContainsString('mem/z:', $flat);
        self::assertStringContainsString('peak/r:', $flat);
        self::assertStringContainsString('peak/z:', $flat);
    }

    #[Test]
    public function renderMetricsTabShowsIntrospectionCounts(): void
    {
        [$overlay, $mountSystem] = $this->makeOverlay();
        $root                   = $this->assertColumn($overlay($this->makeContext($mountSystem)));

        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('comps:', $flat);
        self::assertStringContainsString('sigs:', $flat);
    }

    #[Test]
    public function renderStoreTabShowsSliceInfo(): void
    {
        $store = new AppStore();
        $store->conversation = new ConversationSlice()
            ->addUserMessage('The agora stands.')
            ->appendToken('As does the phalanx.');
        $store->agents = new AgentRegistrySlice()
            ->register(new AgentSummary(id: 'agent_leonidas', name: 'Leonidas', capabilities: ['tactics']));

        [$overlay, $mountSystem] = $this->makeOverlay($store);
        $overlay->activeTab->set(DevToolsTab::Store->value);

        $root = $this->assertColumn($overlay($this->makeContext($mountSystem)));
        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('ConversationSlice', $flat);
        self::assertStringContainsString('AgentRegistrySlice', $flat);
        self::assertStringContainsString('ActivitySlice', $flat);
        self::assertStringContainsString('2', $flat);
        self::assertStringContainsString('1', $flat);
    }

    #[Test]
    public function renderMetricsTabShowsConversationCounts(): void
    {
        $store = new AppStore();
        $store->conversation = new ConversationSlice()
            ->addUserMessage('Leonidas holds the pass.')
            ->appendToken('Three hundred stand firm.');
        $store->activity = new ActivitySlice()->updateUsage(100, 200);

        [$overlay, $mountSystem] = $this->makeOverlay($store);
        $root = $this->assertColumn($overlay($this->makeContext($mountSystem)));
        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('msgs:', $flat);
        self::assertStringContainsString('in:', $flat);
        self::assertStringContainsString('out:', $flat);
        self::assertStringContainsString('total:', $flat);
    }

    #[Test]
    public function tabBarContainsAllTabLabels(): void
    {
        [$overlay, $mountSystem] = $this->makeOverlay();
        $root                   = $this->assertColumn($overlay($this->makeContext($mountSystem)));

        $tabBar = $root->children[0];
        self::assertInstanceOf(TextElement::class, $tabBar);
        self::assertInstanceOf(Line::class, $tabBar->content);

        $flat = $this->flattenLine($tabBar->content);
        self::assertStringContainsString('Metrics', $flat);
        self::assertStringContainsString('Signals', $flat);
        self::assertStringContainsString('Tree', $flat);
        self::assertStringContainsString('Store', $flat);
    }

    #[Test]
    public function renderSignalsTabShowsEmptyStateWhenRegistryDisabled(): void
    {
        [$overlay, $mountSystem] = $this->makeOverlay();
        $overlay->activeTab->set(DevToolsTab::Signals->value);

        $root = $this->assertColumn($overlay($this->makeContext($mountSystem)));
        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('No signals registered', $flat);
    }

    #[Test]
    public function renderSignalsTabShowsRegisteredSignals(): void
    {
        $registry = new SignalRegistry();

        $signal = new Signal(42);
        $registry->register($signal, 'apollo.count');

        [$overlay, $mountSystem] = $this->makeOverlay(registry: $registry);
        $overlay->activeTab->set(DevToolsTab::Signals->value);

        $root = $this->assertColumn($overlay($this->makeContext($mountSystem)));
        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('apollo.count', $flat);
    }

    #[Test]
    public function renderSignalsTabShowsDisposedSignalLabel(): void
    {
        $registry = new SignalRegistry();

        $signal = new Signal(0);
        $registry->register($signal, 'poseidon.depth');
        $signal->dispose();

        [$overlay, $mountSystem] = $this->makeOverlay(registry: $registry);
        $overlay->activeTab->set(DevToolsTab::Signals->value);

        $root = $this->assertColumn($overlay($this->makeContext($mountSystem)));
        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('poseidon.depth', $flat);
    }

    #[Test]
    public function renderTreeTabShowsEmptyStateWhenNoComponents(): void
    {
        [$overlay, $mountSystem] = $this->makeOverlay();
        $overlay->activeTab->set(DevToolsTab::Tree->value);

        $root = $this->assertColumn($overlay($this->makeContext($mountSystem)));
        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('No components mounted', $flat);
    }

    #[Test]
    public function renderTreeTabShowsMountedComponents(): void
    {
        $scope       = $this->createStub(TaskScope::class);
        $mountSystem = new MountSystem($scope);
        $mountSystem->mount(OverlayTreeFixtureComponent::class);

        [$overlay] = $this->makeOverlay(mountSystem: $mountSystem);
        $overlay->activeTab->set(DevToolsTab::Tree->value);

        $root = $this->assertColumn($overlay($this->makeContext($mountSystem)));
        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('OverlayTreeFixtureComponent', $flat);
    }

    /**
     * @return array{DevToolsOverlay, MountSystem}
     */
    private function makeOverlay(
        ?AppStore $store = null,
        ?MountSystem $mountSystem = null,
        ?SignalRegistry $registry = null,
    ): array {
        $store       ??= new AppStore();
        $mountSystem ??= new MountSystem($this->createStub(TaskScope::class));
        $registry    ??= new SignalRegistry();

        return [new DevToolsOverlay($store, $mountSystem, $registry), $mountSystem];
    }

    private function makeContext(?MountSystem $mountSystem = null): RenderContext
    {
        $scope       = $this->createStub(TaskScope::class);
        $mountSystem ??= new MountSystem($scope);

        return new RenderContext($scope, new Ui(), Theme::default(), $mountSystem);
    }

    private function assertColumn(Renderable $element): ColumnElement
    {
        self::assertInstanceOf(ColumnElement::class, $element);

        return $element;
    }

    private function assertPanel(Renderable $element): PanelElement
    {
        self::assertInstanceOf(PanelElement::class, $element);

        return $element;
    }

    private function flattenLines(Renderable $element): string
    {
        if ($element instanceof TextElement) {
            $content = $element->content;

            return $content instanceof Line ? $this->flattenLine($content) : $content;
        }

        if ($element instanceof PanelElement) {
            return $this->flattenLines($element->child);
        }

        if ($element instanceof ColumnElement) {
            return implode(' ', array_map($this->flattenLines(...), $element->children));
        }

        if ($element instanceof RowElement) {
            return implode(' ', array_map($this->flattenLines(...), $element->children));
        }

        return '';
    }

    private function flattenLine(Line $line): string
    {
        return implode('', array_map(
            static fn ($span) => $span->content,
            $line->spans,
        ));
    }
}

final class OverlayTreeFixtureComponent implements Component
{
    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text('');
    }
}
