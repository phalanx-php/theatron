<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit;

use Phalanx\Scope\ExecutionScope;
use Phalanx\Supervisor\TaskRunSnapshot;
use Phalanx\Testing\PhalanxTestCase;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Stage\ScreenMode;
use Phalanx\Theatron\Stage\Stage;
use Phalanx\Theatron\Stage\StageConfig;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\TheatronApp;
use PHPUnit\Framework\Attributes\Test;

final class TheatronAppRenderDiagnosticsTest extends PhalanxTestCase
{
    #[Test]
    public function appDrawLoopUsesNamedRenderDiagnosticTaskForActiveScreen(): void
    {
        AppRenderDiagnosticsProbeScreen::$renderRun = null;

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $app = new TheatronApp(
            Stage::boot(new StageConfig(
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
            )),
            Theme::default(),
            [AppRenderDiagnosticsProbeScreen::class],
            [],
            null,
            false,
        );

        $this->scope->run(static function (ExecutionScope $scope) use ($app): void {
            $app->start($scope);
        });

        self::assertInstanceOf(TaskRunSnapshot::class, AppRenderDiagnosticsProbeScreen::$renderRun);
        self::assertSame(
            'theatron.render.screen ' . AppRenderDiagnosticsProbeScreen::class,
            AppRenderDiagnosticsProbeScreen::$renderRun->name,
        );
        self::assertNull(AppRenderDiagnosticsProbeScreen::$renderRun->currentWait);
    }
}

final class AppRenderDiagnosticsProbeScreen implements Screen
{
    public static ?TaskRunSnapshot $renderRun = null;

    public function __invoke(ScreenContext $ctx): Renderable
    {
        if ($ctx->scope instanceof ExecutionScope) {
            self::$renderRun = $ctx->scope->currentRunSnapshot();
            $ctx->scope->cancellation()->cancel();
        }

        return $ctx->ui->text('app diagnostics');
    }
}
