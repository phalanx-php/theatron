<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Overlay;

use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Template\Slice\PendingEffect;

use function Phalanx\Theatron\Ui\column;
use function Phalanx\Theatron\Ui\divider;
use function Phalanx\Theatron\Ui\panel;
use function Phalanx\Theatron\Ui\text;

class EffectApprovalOverlay implements Component
{
    public function __construct(
        private(set) PendingEffect $effect,
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return panel('Effect Approval', column(
            self::renderEffectInfo($this->effect),
            divider(),
            self::renderArguments($this->effect),
            divider(),
            self::renderHazardLevel($this->effect),
            divider(),
            self::renderActions(),
        ));
    }

    private static function renderEffectInfo(PendingEffect $effect): Renderable
    {
        return column(
            text(sprintf('Kind: %s', $effect->kind)),
            text(sprintf('Summary: %s', $effect->summary)),
        );
    }

    private static function renderArguments(PendingEffect $effect): Renderable
    {
        if ($effect->arguments === []) {
            return text('No arguments');
        }

        $rows = [];
        foreach ($effect->arguments as $key => $value) {
            $rows[] = text(sprintf('%s: %s', $key, $value));
        }

        return column(...$rows);
    }

    private static function renderHazardLevel(PendingEffect $effect): Renderable
    {
        $label = match ($effect->hazardLevel) {
            0 => 'Safe',
            1 => 'Low',
            2 => 'Medium',
            default => 'High',
        };

        return text(sprintf('Hazard: %s', $label));
    }

    private static function renderActions(): Renderable
    {
        return text('[A] Approve  [D] Deny');
    }
}
