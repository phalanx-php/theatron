<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template\Screen;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\DividerElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\RowElement;
use Phalanx\Theatron\Tdom\Element\StatusLineElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Screen\AgentBoardScreen;
use Phalanx\Theatron\Template\Slice\AgentRegistrySlice;
use Phalanx\Theatron\Template\Slice\AgentSummary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AgentBoardScreenTest extends TestCase
{
    #[Test]
    public function rendersEmptyAgentBoard(): void
    {
        $store = new AppStore();
        $screen = new AgentBoardScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);

        self::assertInstanceOf(ColumnElement::class, $result);
        self::assertCount(3, $result->children);

        $cardArea = $result->children[0];
        self::assertInstanceOf(TextElement::class, $cardArea);

        $content = $cardArea->content;
        self::assertIsString($content);
        self::assertStringContainsString('No agents registered', $content);
    }

    #[Test]
    public function rendersAgentCards(): void
    {
        $store = new AppStore();
        $store->agents = new AgentRegistrySlice()
            ->register(new AgentSummary(id: 'agent_zeus', name: 'Zeus', capabilities: ['reasoning']))
            ->register(new AgentSummary(id: 'agent_apollo', name: 'Apollo', capabilities: ['code_gen']));

        $screen = new AgentBoardScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);

        self::assertInstanceOf(ColumnElement::class, $result);

        $cardArea = $result->children[0];
        self::assertInstanceOf(RowElement::class, $cardArea);
        self::assertCount(2, $cardArea->children);

        self::assertInstanceOf(PanelElement::class, $cardArea->children[0]);
        self::assertInstanceOf(PanelElement::class, $cardArea->children[1]);
    }

    #[Test]
    public function showsActiveAgentIndicator(): void
    {
        $store = new AppStore();
        $store->agents = new AgentRegistrySlice()
            ->register(new AgentSummary(id: 'agent_zeus', name: 'Zeus', capabilities: []))
            ->register(new AgentSummary(id: 'agent_apollo', name: 'Apollo', capabilities: []))
            ->activate('agent_zeus');

        $screen = new AgentBoardScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);

        self::assertInstanceOf(ColumnElement::class, $result);

        $cardArea = $result->children[0];
        self::assertInstanceOf(RowElement::class, $cardArea);

        $zeusPanel = $cardArea->children[0];
        self::assertInstanceOf(PanelElement::class, $zeusPanel);

        $zeusBody = $zeusPanel->child;
        self::assertInstanceOf(ColumnElement::class, $zeusBody);

        $texts = array_map(
            static fn ($el) => $el instanceof TextElement && is_string($el->content) ? $el->content : '',
            $zeusBody->children,
        );

        self::assertNotEmpty(array_filter($texts, static fn ($t) => str_contains((string) $t, 'Active')));

        $apolloPanel = $cardArea->children[1];
        self::assertInstanceOf(PanelElement::class, $apolloPanel);

        $apolloBody = $apolloPanel->child;
        self::assertInstanceOf(ColumnElement::class, $apolloBody);

        $apolloTexts = array_map(
            static fn ($el) => $el instanceof TextElement && is_string($el->content) ? $el->content : '',
            $apolloBody->children,
        );

        self::assertEmpty(array_filter($apolloTexts, static fn ($t) => str_contains((string) $t, 'Active')));
    }

    #[Test]
    public function showsCapabilities(): void
    {
        $store = new AppStore();
        $store->agents = new AgentRegistrySlice()
            ->register(new AgentSummary(
                id: 'agent_zeus',
                name: 'Zeus',
                capabilities: ['reasoning', 'tool_use'],
            ));

        $screen = new AgentBoardScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);

        self::assertInstanceOf(ColumnElement::class, $result);

        $cardArea = $result->children[0];
        self::assertInstanceOf(RowElement::class, $cardArea);

        $zeusPanel = $cardArea->children[0];
        self::assertInstanceOf(PanelElement::class, $zeusPanel);

        $zeusBody = $zeusPanel->child;
        self::assertInstanceOf(ColumnElement::class, $zeusBody);

        $combined = implode(' ', array_map(
            static fn ($el) => $el instanceof TextElement && is_string($el->content) ? $el->content : '',
            $zeusBody->children,
        ));

        self::assertStringContainsString('reasoning', $combined);
        self::assertStringContainsString('tool_use', $combined);
    }

    #[Test]
    public function showsAgentCountInStatusBar(): void
    {
        $store = new AppStore();
        $store->agents = new AgentRegistrySlice()
            ->register(new AgentSummary(id: 'agent_zeus', name: 'Zeus', capabilities: []))
            ->register(new AgentSummary(id: 'agent_apollo', name: 'Apollo', capabilities: []));

        $screen = new AgentBoardScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);

        self::assertInstanceOf(ColumnElement::class, $result);
        self::assertCount(3, $result->children);

        self::assertInstanceOf(DividerElement::class, $result->children[1]);

        $statusLine = $result->children[2];
        self::assertInstanceOf(StatusLineElement::class, $statusLine);
        self::assertCount(2, $statusLine->sections);

        $agentCount = $statusLine->sections[1];
        self::assertInstanceOf(TextElement::class, $agentCount);

        $content = $agentCount->content;
        self::assertIsString($content);
        self::assertStringContainsString('2', $content);
    }

    private function makeContext(AppStore $store): ScreenContext
    {
        $scope = $this->createStub(TaskScope::class);
        $navigator = $this->createStub(Navigator::class);
        $mountSystem = new MountSystem($scope);

        return new ScreenContext($scope, new Ui(), Theme::default(), $navigator, $mountSystem);
    }
}
