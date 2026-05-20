<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template\Overlay;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Overlay\DevToolsOverlay;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\AgentRegistrySlice;
use Phalanx\Theatron\Template\Slice\AgentSummary;
use Phalanx\Theatron\Template\Slice\ConversationSlice;
use Phalanx\Theatron\Template\Slice\PendingEffect;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DevToolsOverlayTest extends TestCase
{
    #[Test]
    public function rendersDevToolsPanel(): void
    {
        $store = new AppStore();
        $overlay = new DevToolsOverlay($store);
        $ctx = $this->makeContext();

        $root = $this->assertRootPanel($overlay($ctx));

        self::assertSame('DevTools', $root->title);

        $body = $root->child;
        self::assertInstanceOf(ColumnElement::class, $body);
        self::assertCount(3, $body->children);

        $sub0 = $this->getSubPanel($root, 0);
        $sub1 = $this->getSubPanel($root, 1);
        $sub2 = $this->getSubPanel($root, 2);

        self::assertSame('Conversation', $sub0->title);
        self::assertSame('Agents', $sub1->title);
        self::assertSame('Activity', $sub2->title);
    }

    #[Test]
    public function conversationSectionShowsMessageCount(): void
    {
        $store = new AppStore();
        $store->conversation = new ConversationSlice()
            ->addUserMessage('Sparta does not retreat.')
            ->appendToken('Nor does Olympus forget its oaths.');

        $overlay = new DevToolsOverlay($store);
        $root = $this->assertRootPanel($overlay($this->makeContext()));

        $panel = $this->getSubPanel($root, 0);
        $countText = $this->getPanelBodyRow($panel, 0);

        self::assertSame('Messages: 2', $countText);
    }

    #[Test]
    public function conversationSectionShowsStreamingState(): void
    {
        $store = new AppStore();
        $store->conversation = new ConversationSlice()
            ->addUserMessage('What does Apollo decree?')
            ->appendToken('The oracle of Delphi speaks...');

        self::assertTrue($store->conversation->isStreaming);

        $overlay = new DevToolsOverlay($store);
        $root = $this->assertRootPanel($overlay($this->makeContext()));

        $panel = $this->getSubPanel($root, 0);
        $streamingText = $this->getPanelBodyRow($panel, 1);

        self::assertSame('Streaming: true', $streamingText);
    }

    #[Test]
    public function agentsSectionShowsAgentCount(): void
    {
        $store = new AppStore();
        $store->agents = new AgentRegistrySlice()
            ->register(new AgentSummary(id: 'agent_leonidas', name: 'Leonidas', capabilities: ['combat', 'tactics']))
            ->register(new AgentSummary(id: 'agent_themistocles', name: 'Themistocles', capabilities: ['naval']))
            ->activate('agent_leonidas');

        $overlay = new DevToolsOverlay($store);
        $root = $this->assertRootPanel($overlay($this->makeContext()));

        $panel = $this->getSubPanel($root, 1);
        $countText = $this->getPanelBodyRow($panel, 0);
        $activeText = $this->getPanelBodyRow($panel, 1);

        self::assertSame('Registered: 2', $countText);
        self::assertSame('Active: agent_leonidas', $activeText);
    }

    #[Test]
    public function activitySectionShowsStatus(): void
    {
        $store = new AppStore();
        $store->activity = new ActivitySlice()->awaitingApproval(
            new PendingEffect(kind: 'read_file', summary: 'Read the Spartan codex', arguments: [], hazardLevel: 1),
        );

        $overlay = new DevToolsOverlay($store);
        $root = $this->assertRootPanel($overlay($this->makeContext()));

        $panel = $this->getSubPanel($root, 2);
        $statusText = $this->getPanelBodyRow($panel, 0);

        self::assertSame('Status: Awaiting Approval', $statusText);
    }

    #[Test]
    public function activitySectionShowsTokenCounts(): void
    {
        $store = new AppStore();
        $store->activity = new ActivitySlice()->updateUsage(512, 1024);

        $overlay = new DevToolsOverlay($store);
        $root = $this->assertRootPanel($overlay($this->makeContext()));

        $panel = $this->getSubPanel($root, 2);
        $tokenText = $this->getPanelBodyRow($panel, 2);

        self::assertSame('Tokens: 512 in / 1024 out / 1536 total', $tokenText);
    }

    private function makeContext(): RenderContext
    {
        $scope = $this->createStub(TaskScope::class);
        $mountSystem = new MountSystem($scope);

        return new RenderContext($scope, new Ui(), Theme::default(), $mountSystem);
    }

    private function assertRootPanel(mixed $result): PanelElement
    {
        self::assertInstanceOf(PanelElement::class, $result);
        return $result;
    }

    private function getSubPanel(PanelElement $root, int $index): PanelElement
    {
        $body = $root->child;
        self::assertInstanceOf(ColumnElement::class, $body);

        $sub = $body->children[$index];
        self::assertInstanceOf(PanelElement::class, $sub);

        return $sub;
    }

    private function getPanelBodyRow(PanelElement $panel, int $index): string
    {
        $body = $panel->child;
        self::assertInstanceOf(ColumnElement::class, $body);

        $row = $body->children[$index];
        self::assertInstanceOf(TextElement::class, $row);

        $content = $row->content;
        self::assertIsString($content);

        return $content;
    }
}
