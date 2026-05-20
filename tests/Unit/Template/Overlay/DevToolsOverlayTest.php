<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template\Overlay;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Layout\Border;
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
        $overlay = new DevToolsOverlay(new AppStore());
        $root    = $this->assertColumn($overlay($this->makeContext()));

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
        $overlay = new DevToolsOverlay(new AppStore());

        self::assertSame(DevToolsTab::Metrics->value, $overlay->activeTab->value);
    }

    #[Test]
    public function renderMetricsTabShowsMemory(): void
    {
        $overlay = new DevToolsOverlay(new AppStore());
        $root    = $this->assertColumn($overlay($this->makeContext()));

        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('mem:', $flat);
        self::assertStringContainsString('peak:', $flat);
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

        $overlay = new DevToolsOverlay($store);
        $overlay->activeTab->value = DevToolsTab::Store->value;

        $root = $this->assertColumn($overlay($this->makeContext()));
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

        $overlay = new DevToolsOverlay($store);
        $root    = $this->assertColumn($overlay($this->makeContext()));
        $body    = $this->assertPanel($root->children[1]);
        $flat    = $this->flattenLines($body->child);

        self::assertStringContainsString('msgs:', $flat);
        self::assertStringContainsString('in:', $flat);
        self::assertStringContainsString('out:', $flat);
        self::assertStringContainsString('total:', $flat);
    }

    #[Test]
    public function tabBarContainsAllTabLabels(): void
    {
        $overlay = new DevToolsOverlay(new AppStore());
        $root    = $this->assertColumn($overlay($this->makeContext()));

        $tabBar = $root->children[0];
        self::assertInstanceOf(TextElement::class, $tabBar);
        self::assertInstanceOf(Line::class, $tabBar->content);

        $flat = $this->flattenLine($tabBar->content);
        self::assertStringContainsString('Metrics', $flat);
        self::assertStringContainsString('Store', $flat);
        self::assertStringContainsString('Info', $flat);
    }

    #[Test]
    public function infoTabRendersPlaceholderContent(): void
    {
        $overlay = new DevToolsOverlay(new AppStore());
        $overlay->activeTab->value = DevToolsTab::Info->value;

        $root = $this->assertColumn($overlay($this->makeContext()));
        $body = $this->assertPanel($root->children[1]);
        $flat = $this->flattenLines($body->child);

        self::assertStringContainsString('phalanx-theatron', $flat);
        self::assertStringContainsString('Metrics', $flat);
        self::assertStringContainsString('Store', $flat);
    }

    private function makeContext(): RenderContext
    {
        $scope       = $this->createStub(TaskScope::class);
        $mountSystem = new MountSystem($scope);

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
