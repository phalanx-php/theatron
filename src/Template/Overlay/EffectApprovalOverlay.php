<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Overlay;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Agent\AgentRuntime;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Contract\Mountable;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Input\NormalModeHandler;
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\PendingEffect;

use function Phalanx\Theatron\Ui\column;
use function Phalanx\Theatron\Ui\divider;
use function Phalanx\Theatron\Ui\panel;
use function Phalanx\Theatron\Ui\text;

class EffectApprovalOverlay implements Component, NormalModeHandler, Mountable
{
    private ?TaskScope $scope = null;

    public function __construct(
        private(set) PendingEffect $effect,
        private ?AppStore $store = null,
        private ?Navigator $navigator = null,
        private ?AgentRuntime $runtime = null,
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

    public function onMount(TaskScope $scope): void
    {
        $this->scope = $scope;
    }

    public function onUnmount(): void
    {
        $this->scope = null;
    }

    public function handleNormalKey(KeyEvent $event): bool
    {
        if ($event->is('a') || $event->is('A')) {
            if ($this->scope !== null && $this->runtime !== null) {
                $this->runtime->approve($this->scope, $this->effect);
            } elseif ($this->store !== null) {
                $this->store->activity = $this->store->activity->effectResolved();
            }
            $this->navigator?->dismiss();

            return true;
        }

        if ($event->is('d') || $event->is('D')) {
            if ($this->scope !== null && $this->runtime !== null) {
                $this->runtime->deny($this->scope, $this->effect);
            } elseif ($this->store !== null) {
                $this->store->activity = $this->store->activity->effectResolved();
            }
            $this->navigator?->dismiss();

            return true;
        }

        return false;
    }

    private static function renderEffectInfo(PendingEffect $effect): Renderable
    {
        return column(
            text(sprintf('Kind: %s', $effect->kind)),
            text(sprintf('Effect: %s', $effect->effectId !== '' ? $effect->effectId : 'unknown')),
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
            $rows[] = text(sprintf('%s: %s', $key, self::formatValue($value)));
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

    private static function formatValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
