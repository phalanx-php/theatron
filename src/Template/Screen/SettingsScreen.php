<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Screen;

use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Input\InputMode;
use Phalanx\Theatron\Kit\StatusBar;
use Phalanx\Theatron\Layout\Border;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style as TdomStyle;
use Phalanx\Theatron\Tdom\Ui;

/**
 * Display-only settings screen showing provider, model, and activity configuration.
 * Values are hardcoded defaults; interactive editing is deferred until a later iteration.
 */
class SettingsScreen implements Screen
{
    public function __invoke(ScreenContext $ctx): Renderable
    {
        return $ctx->ui->column(
            self::renderProviderPanel($ctx->ui),
            $ctx->ui->divider(),
            self::renderModelPanel($ctx->ui),
            $ctx->ui->divider(),
            self::renderActivityPanel($ctx->ui),
            $ctx->ui->divider(),
            self::renderStatusBar($ctx->ui, InputMode::Normal),
        );
    }

    private static function panelStyle(): TdomStyle
    {
        return TdomStyle::of(border: Border::Rounded, color: Color::indexed(240));
    }

    private static function modeLabel(InputMode $mode): string
    {
        return match ($mode) {
            InputMode::Normal => ' NORMAL ',
            InputMode::Insert => ' INSERT ',
        };
    }

    private static function modeColor(InputMode $mode): Color
    {
        return match ($mode) {
            InputMode::Normal => Color::brightCyan(),
            InputMode::Insert => Color::brightGreen(),
        };
    }

    private static function renderProviderPanel(Ui $ui): Renderable
    {
        return $ui->panel(
            'Provider',
            $ui->column(
                $ui->text('Type: Ollama'),
                $ui->text('URL: http://localhost:11434'),
            ),
            style: self::panelStyle(),
        );
    }

    private static function renderModelPanel(Ui $ui): Renderable
    {
        return $ui->panel(
            'Model',
            $ui->column(
                $ui->text('Name: qwen2.5-coder:7b'),
            ),
            style: self::panelStyle(),
        );
    }

    private static function renderActivityPanel(Ui $ui): Renderable
    {
        return $ui->panel(
            'Activity',
            $ui->column(
                $ui->text('Max Invocations: 3'),
                $ui->text('Timeout: none'),
            ),
            style: self::panelStyle(),
        );
    }

    private static function renderStatusBar(Ui $ui, InputMode $mode): Renderable
    {
        return StatusBar::new()
            ->section(self::modeLabel($mode), self::modeColor($mode))
            ->left('Settings')
            ->render($ui);
    }
}
