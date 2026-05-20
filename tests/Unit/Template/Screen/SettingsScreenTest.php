<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template\Screen;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\HasStatusBar;
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\DividerElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\StatusLineElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Screen\SettingsScreen;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SettingsScreenTest extends TestCase
{
    #[Test]
    public function rendersSettingsLayout(): void
    {
        $screen = new SettingsScreen(new AppStore());
        $ctx = $this->makeContext();

        $result = $screen($ctx);

        self::assertInstanceOf(ColumnElement::class, $result);

        $children = $result->children;
        self::assertCount(5, $children);

        self::assertInstanceOf(PanelElement::class, $children[0]);
        self::assertInstanceOf(DividerElement::class, $children[1]);
        self::assertInstanceOf(PanelElement::class, $children[2]);
        self::assertInstanceOf(DividerElement::class, $children[3]);
        self::assertInstanceOf(PanelElement::class, $children[4]);
    }

    #[Test]
    public function implementsHasStatusBar(): void
    {
        self::assertInstanceOf(HasStatusBar::class, new SettingsScreen(new AppStore()));
    }

    #[Test]
    public function statusBarRendersNormalModeAndLabel(): void
    {
        $screen = new SettingsScreen(new AppStore());
        $ui = new Ui();

        $result = $screen->statusBar($ui);

        self::assertInstanceOf(StatusLineElement::class, $result);
    }

    #[Test]
    public function providerSectionShowsDefaults(): void
    {
        $screen = new SettingsScreen(new AppStore());
        $ctx = $this->makeContext();

        $result = $screen($ctx);
        self::assertInstanceOf(ColumnElement::class, $result);

        $providerPanel = $result->children[0];
        self::assertInstanceOf(PanelElement::class, $providerPanel);
        self::assertSame('Provider', $providerPanel->title);

        $inner = $providerPanel->child;
        self::assertInstanceOf(ColumnElement::class, $inner);

        $typeText = $inner->children[0];
        self::assertInstanceOf(TextElement::class, $typeText);
        self::assertIsString($typeText->content);
        self::assertStringContainsString('Ollama', $typeText->content);

        $urlText = $inner->children[1];
        self::assertInstanceOf(TextElement::class, $urlText);
        self::assertIsString($urlText->content);
        self::assertStringContainsString('localhost', $urlText->content);
    }

    #[Test]
    public function modelSectionShowsDefault(): void
    {
        $screen = new SettingsScreen(new AppStore());
        $ctx = $this->makeContext();

        $result = $screen($ctx);
        self::assertInstanceOf(ColumnElement::class, $result);

        $modelPanel = $result->children[2];
        self::assertInstanceOf(PanelElement::class, $modelPanel);
        self::assertSame('Model', $modelPanel->title);

        $inner = $modelPanel->child;
        self::assertInstanceOf(ColumnElement::class, $inner);

        $nameText = $inner->children[0];
        self::assertInstanceOf(TextElement::class, $nameText);
        self::assertIsString($nameText->content);
        self::assertStringContainsString('qwen2.5-coder:7b', $nameText->content);
    }

    #[Test]
    public function activitySectionShowsDefaults(): void
    {
        $screen = new SettingsScreen(new AppStore());
        $ctx = $this->makeContext();

        $result = $screen($ctx);
        self::assertInstanceOf(ColumnElement::class, $result);

        $activityPanel = $result->children[4];
        self::assertInstanceOf(PanelElement::class, $activityPanel);
        self::assertSame('Activity', $activityPanel->title);

        $inner = $activityPanel->child;
        self::assertInstanceOf(ColumnElement::class, $inner);

        $invocationsText = $inner->children[0];
        self::assertInstanceOf(TextElement::class, $invocationsText);
        self::assertIsString($invocationsText->content);
        self::assertStringContainsString('3', $invocationsText->content);

        $timeoutText = $inner->children[1];
        self::assertInstanceOf(TextElement::class, $timeoutText);
        self::assertIsString($timeoutText->content);
        self::assertStringContainsString('none', $timeoutText->content);
    }

    private function makeContext(): ScreenContext
    {
        $scope = $this->createStub(TaskScope::class);
        $navigator = $this->createStub(Navigator::class);
        $mountSystem = new MountSystem($scope);

        return new ScreenContext($scope, new Ui(), Theme::default(), $navigator, $mountSystem);
    }
}
