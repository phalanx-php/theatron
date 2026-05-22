<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template\Screen;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\InputElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\RowElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Screen\ChatScreen;
use Phalanx\Theatron\Template\Screen\DevToolsScreen;
use Phalanx\Theatron\Template\Screen\LlmRequestDetailScreen;
use Phalanx\Theatron\Template\Slice\LlmRequestEntry;
use Phalanx\Theatron\Text\Line;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DevToolsScreenTest extends TestCase
{
    #[Test]
    public function rendersStoreRuntimeAndLlmRequestColumns(): void
    {
        $store = new AppStore();
        $store->requests = $store->requests->append($this->request('req-1', '/api/chat'));
        $navigator = new RecordingNavigator();
        $screen = new DevToolsScreen($store, new MountSystem($this->createStub(TaskScope::class)), $navigator);

        $result = $screen($this->makeContext($navigator));

        self::assertInstanceOf(RowElement::class, $result);

        $text = self::flatten($result);
        self::assertStringContainsString('DevTools', $text);
        self::assertStringContainsString('Store Slices', $text);
        self::assertStringContainsString('Memory (ZMM)', $text);
        self::assertStringContainsString('Runtime', $text);
        self::assertStringContainsString('LLM Requests', $text);
        self::assertStringContainsString('POST /api/chat', $text);
    }

    #[Test]
    public function requestNavigationAndEnterOpenDetailPage(): void
    {
        $store = new AppStore();
        $store->requests = $store->requests
            ->append($this->request('req-1', '/api/first'))
            ->append($this->request('req-2', '/api/second'));
        $navigator = new RecordingNavigator();
        $screen = new DevToolsScreen($store, new MountSystem($this->createStub(TaskScope::class)), $navigator);

        self::assertSame(1, $store->requests->focusedIndex);

        self::assertTrue($screen->handleNormalKey(new KeyEvent(Key::Up)));
        self::assertSame(0, $store->requests->focusedIndex);

        self::assertTrue($screen->handleNormalKey(new KeyEvent(Key::Enter)));
        self::assertSame(LlmRequestDetailScreen::class, $navigator->lastScreen);
        self::assertSame(0, $store->requests->detailScrollOffset);
    }

    #[Test]
    public function statusBarRendersRequestControls(): void
    {
        $screen = new DevToolsScreen(
            new AppStore(),
            new MountSystem($this->createStub(TaskScope::class)),
            new RecordingNavigator(),
        );

        $text = self::flatten($screen->statusBar(new Ui()));

        self::assertStringContainsString('↑ req', $text);
        self::assertStringContainsString('↓ req', $text);
        self::assertStringContainsString('Enter detail', $text);
        self::assertStringContainsString('Esc back', $text);
    }

    private static function flatten(Renderable|string $renderable): string
    {
        if (is_string($renderable)) {
            return $renderable;
        }

        if ($renderable instanceof TextElement) {
            return self::lineToText($renderable->content);
        }

        if ($renderable instanceof InputElement) {
            return self::lineToText($renderable->prompt) . $renderable->value;
        }

        if ($renderable instanceof ColumnElement || $renderable instanceof RowElement) {
            return implode("\n", array_map(self::flatten(...), $renderable->children));
        }

        if ($renderable instanceof PanelElement) {
            return self::lineToText($renderable->title) . "\n" . self::flatten($renderable->child);
        }

        return '';
    }

    private static function lineToText(string|Line $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        return implode('', array_map(static fn($span): string => $span->content, $content->spans));
    }

    private function request(string $id, string $path): LlmRequestEntry
    {
        return new LlmRequestEntry(
            requestId: $id,
            method: 'POST',
            path: $path,
            status: 200,
            elapsedMs: 39_497.0,
            tokenCount: 312,
            requestBody: '{"model":"qwen3:4b"}',
            responseBody: '{"message":"Strategic Guidance"}',
            complete: true,
        );
    }

    private function makeContext(Navigator $navigator): ScreenContext
    {
        $scope = $this->createStub(TaskScope::class);
        $mountSystem = new MountSystem($scope);

        return new ScreenContext($scope, new Ui(), Theme::default(), $navigator, $mountSystem);
    }
}

final class RecordingNavigator implements Navigator
{
    /** @var class-string<Screen>|null */
    public ?string $lastScreen = null;

    /** @param class-string<Screen> $screen */
    public function go(string $screen): void
    {
        $this->lastScreen = $screen;
    }

    public function back(): bool
    {
        return false;
    }

    /** @param class-string<Component> $component */
    public function overlay(string $component, mixed ...$params): void
    {
    }

    public function dismiss(): void
    {
    }

    public function dismissAll(): void
    {
    }

    /** @return class-string<Screen> */
    public function active(): string
    {
        return ChatScreen::class;
    }
}
