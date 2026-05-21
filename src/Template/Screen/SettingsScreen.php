<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Screen;

use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\HasStatusBar;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Kit\StatusBar;
use Phalanx\Theatron\Layout\Border;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style as TdomStyle;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;

class SettingsScreen implements Screen, HasStatusBar
{
    public function __construct(
        private(set) AppStore $store,
    ) {
    }

    public function __invoke(ScreenContext $ctx): Renderable
    {
        return $ctx->ui->column(
            self::renderProviderPanel($ctx->ui, $ctx->theme),
            $ctx->ui->divider(),
            self::renderModelPanel($ctx->ui, $ctx->theme),
            $ctx->ui->divider(),
            self::renderActivityPanel($ctx->ui, $ctx->theme),
        );
    }

    public function statusBar(Ui $ui): Renderable
    {
        $mode = $this->store->inputMode->mode;

        return StatusBar::new()
            ->section($mode->label(), $mode->color())
            ->left('Settings')
            ->render($ui);
    }

    private static function panelStyle(Theme $theme): TdomStyle
    {
        return TdomStyle::of(border: Border::Rounded, color: $theme->border);
    }

    private static function renderProviderPanel(Ui $ui, Theme $theme): Renderable
    {
        return $ui->panel(
            'Provider',
            $ui->column(
                $ui->text('Type: Ollama'),
                $ui->text('URL: http://localhost:11434'),
            ),
            style: self::panelStyle($theme),
        );
    }

    private static function renderModelPanel(Ui $ui, Theme $theme): Renderable
    {
        return $ui->panel(
            'Model',
            $ui->column(
                $ui->text('Name: qwen2.5-coder:7b'),
            ),
            style: self::panelStyle($theme),
        );
    }

    private static function renderActivityPanel(Ui $ui, Theme $theme): Renderable
    {
        return $ui->panel(
            'Activity',
            $ui->column(
                $ui->text('Max Invocations: 3'),
                $ui->text('Timeout: none'),
            ),
            style: self::panelStyle($theme),
        );
    }
}
