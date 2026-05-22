<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template;

use Phalanx\Scope\ExecutionScope;
use Phalanx\Scope\TaskScope;
use Phalanx\Testing\PhalanxTestCase;
use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Binding\BindingAction;
use Phalanx\Theatron\Binding\BindingRegistry;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Input\ModeDispatcher;
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Reactive\SignalRegistry;
use Phalanx\Theatron\Stage\ScreenMode;
use Phalanx\Theatron\Stage\StageConfig;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\InputElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\RowElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Screen\AgentBoardScreen;
use Phalanx\Theatron\Template\Screen\ChatScreen;
use Phalanx\Theatron\Template\Screen\DevToolsScreen;
use Phalanx\Theatron\Template\Screen\LlmRequestDetailScreen;
use Phalanx\Theatron\Template\Screen\SettingsScreen;
use Phalanx\Theatron\Template\Slice\DevToolsTab;
use Phalanx\Theatron\Template\Slice\LlmRequestEntry;
use Phalanx\Theatron\Template\Slice\SettingsTab;
use Phalanx\Theatron\Text\Line;
use Phalanx\Theatron\Theatron;
use Phalanx\Theatron\TheatronApp;
use Phalanx\Theatron\TheatronServiceBundle;
use PHPUnit\Framework\Attributes\Test;

final class TemplateAppReadinessTest extends PhalanxTestCase
{
    #[Test]
    public function templateAppDrawsFirstFrameWithBinBootstrapShape(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $app = self::templateBuilder($stream)->build();
        $testApp = $this->testApp([], new TheatronServiceBundle($app));

        $testApp->application->scoped(static function (ExecutionScope $scope) use ($app, $stream): void {
            $canceller = null;
            $attempts = 0;
            $canceller = $scope->periodic(0.01, static function () use (
                $scope,
                $stream,
                &$attempts,
                &$canceller,
            ): void {
                $attempts++;

                if (!str_contains(self::streamText($stream), 'Theatron') && $attempts < 50) {
                    return;
                }

                $canceller?->cancel();
                $scope->cancellation()->cancel();
            });

            $app->start($scope);

            rewind($stream);
            $output = self::stripAnsi((string) stream_get_contents($stream));

            self::assertStringContainsString('Theatron', $output);
            self::assertStringContainsString('Powered by Phalanx PHP', $output);
            self::assertStringContainsString('Type a message to begin.', $output);
            self::assertStringContainsString('+>', $output);
            self::assertStringContainsString('^D devtools', $output);
            self::assertStringContainsString('^S settings', $output);
            self::assertStringNotContainsString('DevToolsOverlay', $output);
        });
    }

    #[Test]
    public function templateBuilderRegistersExpectedScreensBindingsAndDevtools(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $builder = self::templateBuilder($stream);
        $app = $builder->build();

        self::assertSame(self::templateScreens(), $builder->registeredScreens());
        self::assertCount(3, $builder->registeredGlobalBindings());
        self::assertCount(1, $builder->registeredProviders());
        self::assertSame(AppStore::class, $builder->registeredStore());
        self::assertTrue($app->devtools);
        self::assertInstanceOf(SignalRegistry::class, $app->registry);

        $registry = new BindingRegistry();
        $registry->setGlobal($builder->registeredGlobalBindings());

        $devtools = $registry->resolve(new KeyEvent('d', ctrl: true));
        $settings = $registry->resolve(new KeyEvent('s', ctrl: true));

        self::assertInstanceOf(BindingAction::class, $devtools?->action);
        self::assertSame(DevToolsScreen::class, $devtools->action->target);
        self::assertInstanceOf(BindingAction::class, $settings?->action);
        self::assertSame(SettingsScreen::class, $settings->action->target);
    }

    #[Test]
    public function templateScreensRenderInspectionDetailAndSettingsReadiness(): void
    {
        $store = new AppStore();
        $store->requests = $store->requests->append(new LlmRequestEntry(
            requestId: 'req-1',
            method: 'POST',
            path: '/api/chat',
            status: 200,
            elapsedMs: 39_497.0,
            tokenCount: 312,
            requestBody: '{"model":"qwen3:4b"}',
            responseBody: '{"message":"Strategic Guidance"}',
            complete: true,
        ));

        $scope = $this->createStub(TaskScope::class);
        $mountSystem = new MountSystem($scope, registry: new SignalRegistry());
        $navigator = new TemplateReadinessNavigator();
        $context = new ScreenContext($scope, Theme::default(), $navigator, $mountSystem);

        $devtools = new DevToolsScreen($store, $mountSystem, $navigator, new SignalRegistry());
        $detail = new LlmRequestDetailScreen($store);
        $settings = new SettingsScreen($store);

        $devtoolsText = self::flatten($devtools($context));
        self::assertStringContainsString('Metrics', $devtoolsText);
        self::assertStringContainsString('Requests', $devtoolsText);
        self::assertStringContainsString('Signals', $devtoolsText);
        self::assertStringContainsString('Tree', $devtoolsText);
        self::assertStringContainsString('Store', $devtoolsText);
        self::assertStringContainsString('POST /api/chat', $devtoolsText);

        self::assertTrue($devtools->handleNormalKey(new KeyEvent(Key::Right)));
        self::assertSame(DevToolsTab::Requests, $store->devtools->activeTab);
        self::assertTrue($devtools->handleNormalKey(new KeyEvent(Key::Enter)));
        self::assertSame(LlmRequestDetailScreen::class, $navigator->lastScreen);

        $detailText = self::flatten($detail($context));
        self::assertStringContainsString('POST /api/chat', $detailText);
        self::assertStringContainsString('Request Body', $detailText);
        self::assertStringContainsString('Response Body', $detailText);
        self::assertStringContainsString('Strategic Guidance', $detailText);

        $dispatcher = new ModeDispatcher(new \Phalanx\Theatron\Focus\FocusManager());
        $dispatcher->focus->register('settings', $settings);
        self::assertTrue($dispatcher->dispatch(new KeyEvent(Key::Right)));
        self::assertSame(SettingsTab::Tools, $store->settings->activeTab);

        $settingsText = self::flatten($settings($context));
        self::assertStringContainsString('Settings', $settingsText);
        self::assertStringContainsString('Tools', $settingsText);
    }

    /**
     * @param resource $stream
     */
    private static function templateBuilder(mixed $stream): \Phalanx\Theatron\TheatronBuilder
    {
        return Theatron::app(['APP_ENV' => 'test'])
            ->store(AppStore::class)
            ->screens(self::templateScreens())
            ->globalBindings(self::templateBindings())
            ->stageConfig(new StageConfig(
                screenMode: ScreenMode::Inline,
                bracketedPaste: false,
                handleInput: false,
                defaultExitHandler: false,
                activeIntervalUs: 1_000,
                stream: $stream,
                env: [
                    'COLUMNS' => '100',
                    'LINES' => '30',
                ],
            ))
            ->providers(
                static fn(TheatronApp $app): TheatronServiceBundle => new TheatronServiceBundle($app),
            )
            ->devtools();
    }

    /** @return list<class-string<Screen>> */
    private static function templateScreens(): array
    {
        return [
            ChatScreen::class,
            AgentBoardScreen::class,
            DevToolsScreen::class,
            LlmRequestDetailScreen::class,
            SettingsScreen::class,
        ];
    }

    /** @return list<Binding> */
    private static function templateBindings(): array
    {
        return [
            Binding::ctrl('c')->quit()->label('quit'),
            Binding::ctrl('d')->workspace(DevToolsScreen::class)->label('devtools'),
            Binding::ctrl('s')->workspace(SettingsScreen::class)->label('settings'),
        ];
    }

    private static function stripAnsi(string $text): string
    {
        return (string) preg_replace('/\x1B(?:[@-Z\\\\-_]|\[[0-?]*[ -\/]*[@-~])/', '', $text);
    }

    /**
     * @param resource $stream
     */
    private static function streamText(mixed $stream): string
    {
        $position = ftell($stream);
        rewind($stream);
        $text = self::stripAnsi((string) stream_get_contents($stream));

        if (is_int($position)) {
            fseek($stream, $position);
        }

        return $text;
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
}

final class TemplateReadinessNavigator implements Navigator
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
