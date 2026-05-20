<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Screen;

use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;

class SettingsScreen implements Screen
{
    public function __invoke(ScreenContext $ctx): Renderable
    {
        return $ctx->ui->column(
            self::renderProviderSection($ctx->ui),
            $ctx->ui->divider(),
            self::renderModelSection($ctx->ui),
            $ctx->ui->divider(),
            self::renderActivitySection($ctx->ui),
            $ctx->ui->divider(),
            self::renderStatusBar($ctx->ui),
        );
    }

    private static function renderProviderSection(Ui $ui): Renderable
    {
        return $ui->panel(
            'Provider',
            $ui->column(
                $ui->text('Type: Ollama'),
                $ui->text('URL: http://localhost:11434'),
            ),
        );
    }

    private static function renderModelSection(Ui $ui): Renderable
    {
        return $ui->panel(
            'Model',
            $ui->column(
                $ui->text('Name: qwen2.5-coder:7b'),
            ),
        );
    }

    private static function renderActivitySection(Ui $ui): Renderable
    {
        return $ui->panel(
            'Activity',
            $ui->column(
                $ui->text('Max Invocations: 3'),
                $ui->text('Timeout: none'),
            ),
        );
    }

    private static function renderStatusBar(Ui $ui): Renderable
    {
        return $ui->statusLine(
            $ui->text('Settings'),
        );
    }
}
