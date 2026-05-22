<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit;

use Phalanx\Scope\ExecutionScope;
use Phalanx\Testing\PhalanxTestCase;
use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Input\NormalModeHandler;
use Phalanx\Theatron\Stage\ScreenMode;
use Phalanx\Theatron\Stage\Stage;
use Phalanx\Theatron\Stage\StageConfig;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\TheatronApp;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

use function Phalanx\Theatron\Ui\text;

final class TheatronAppInputTest extends PhalanxTestCase
{
    #[Test]
    public function overlayNormalHandlerReceivesKeysBeforeGlobalBindings(): void
    {
        OverlayPriorityProbe::$handled = 0;
        OverlayPriorityProbe::$global = 0;

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $stage = Stage::boot(new StageConfig(
            screenMode: ScreenMode::Inline,
            bracketedPaste: false,
            handleInput: false,
            defaultExitHandler: false,
            activeIntervalUs: 1_000,
            stream: $stream,
            env: [
                'COLUMNS' => '20',
                'LINES' => '5',
            ],
        ));
        $app = new TheatronApp(
            $stage,
            Theme::default(),
            [OverlayPriorityScreen::class],
            [
                Binding::key('o')->toggle(OverlayPriorityProbe::class),
                Binding::key('a')->action(static function (): void {
                    OverlayPriorityProbe::$global++;
                }),
            ],
            null,
            false,
        );

        $this->scope->run(static function (ExecutionScope $scope) use ($app, $stage): void {
            $scope->go(static function () use ($app, $scope): void {
                $app->start($scope);
            }, 'theatron-app');
            $scope->delay(0.01);

            self::dispatchInput($stage, new KeyEvent(key: 'o'));
            self::dispatchInput($stage, new KeyEvent(key: 'a'));

            $scope->cancellation()->cancel();
        });

        self::assertSame(1, OverlayPriorityProbe::$handled);
        self::assertSame(0, OverlayPriorityProbe::$global);
    }

    private static function dispatchInput(Stage $stage, KeyEvent $event): void
    {
        $method = new ReflectionMethod($stage, 'dispatchInput');
        $method->invoke($stage, $event);
    }
}

final class OverlayPriorityScreen implements Screen
{
    public function __invoke(ScreenContext $ctx): Renderable
    {
        return text('screen');
    }
}

final class OverlayPriorityProbe implements Component, NormalModeHandler
{
    public static int $handled = 0;

    public static int $global = 0;

    public function __invoke(RenderContext $ctx): Renderable
    {
        return text('overlay');
    }

    public function handleNormalKey(KeyEvent $event): bool
    {
        if (!$event->is('a')) {
            return false;
        }

        self::$handled++;

        return true;
    }
}
