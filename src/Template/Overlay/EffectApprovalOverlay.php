<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Overlay;

use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\Slice\PendingEffect;

class EffectApprovalOverlay implements Component
{
    public function __construct(
        private(set) PendingEffect $effect,
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->panel('Effect Approval', $ctx->ui->column(
            self::renderEffectInfo($ctx->ui, $this->effect),
            $ctx->ui->divider(),
            self::renderArguments($ctx->ui, $this->effect),
            $ctx->ui->divider(),
            self::renderHazardLevel($ctx->ui, $this->effect),
            $ctx->ui->divider(),
            self::renderActions($ctx->ui),
        ));
    }

    private static function renderEffectInfo(Ui $ui, PendingEffect $effect): Renderable
    {
        return $ui->column(
            $ui->text(sprintf('Kind: %s', $effect->kind)),
            $ui->text(sprintf('Summary: %s', $effect->summary)),
        );
    }

    private static function renderArguments(Ui $ui, PendingEffect $effect): Renderable
    {
        if ($effect->arguments === []) {
            return $ui->text('No arguments');
        }

        $rows = [];
        foreach ($effect->arguments as $key => $value) {
            $rows[] = $ui->text(sprintf('%s: %s', $key, $value));
        }

        return $ui->column(...$rows);
    }

    private static function renderHazardLevel(Ui $ui, PendingEffect $effect): Renderable
    {
        $label = match ($effect->hazardLevel) {
            0 => 'Safe',
            1 => 'Low',
            2 => 'Medium',
            default => 'High',
        };

        return $ui->text(sprintf('Hazard: %s', $label));
    }

    private static function renderActions(Ui $ui): Renderable
    {
        return $ui->text('[A] Approve  [D] Deny');
    }
}
