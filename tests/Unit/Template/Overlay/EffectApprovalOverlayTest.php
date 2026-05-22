<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template\Overlay;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\DividerElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Template\Overlay\EffectApprovalOverlay;
use Phalanx\Theatron\Template\Slice\PendingEffect;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EffectApprovalOverlayTest extends TestCase
{
    #[Test]
    public function rendersEffectPanel(): void
    {
        $effect = new PendingEffect(
            kind: 'file.write',
            summary: 'Write to /olympus/config.php',
            arguments: [],
            hazardLevel: 0,
        );

        $result = (new EffectApprovalOverlay($effect))($this->makeContext());

        self::assertInstanceOf(PanelElement::class, $result);
        self::assertSame('Effect Approval', $result->title);
    }

    #[Test]
    public function showsEffectKind(): void
    {
        $effect = new PendingEffect(
            kind: 'file.write',
            summary: 'Write to /olympus/config.php',
            arguments: [],
            hazardLevel: 0,
        );

        $result = (new EffectApprovalOverlay($effect))($this->makeContext());

        self::assertStringContainsString('file.write', $this->flattenText($result));
    }

    #[Test]
    public function showsEffectSummary(): void
    {
        $effect = new PendingEffect(
            kind: 'file.write',
            summary: 'Write to /olympus/config.php',
            arguments: [],
            hazardLevel: 0,
        );

        $result = (new EffectApprovalOverlay($effect))($this->makeContext());

        self::assertStringContainsString('Write to /olympus/config.php', $this->flattenText($result));
    }

    #[Test]
    public function showsArguments(): void
    {
        $effect = new PendingEffect(
            kind: 'process.run',
            summary: 'Execute the phalanx formation script',
            arguments: ['path' => '/sparta/bin/formation.sh', 'flags' => '--shield-wall'],
            hazardLevel: 2,
        );

        $result = (new EffectApprovalOverlay($effect))($this->makeContext());
        $text = $this->flattenText($result);

        self::assertStringContainsString('path', $text);
        self::assertStringContainsString('/sparta/bin/formation.sh', $text);
        self::assertStringContainsString('flags', $text);
        self::assertStringContainsString('--shield-wall', $text);
    }

    #[Test]
    public function showsEmptyArgumentsMessage(): void
    {
        $effect = new PendingEffect(
            kind: 'file.read',
            summary: 'Read the Spartan codex',
            arguments: [],
            hazardLevel: 0,
        );

        $result = (new EffectApprovalOverlay($effect))($this->makeContext());

        self::assertStringContainsString('No arguments', $this->flattenText($result));
    }

    #[Test]
    public function showsHazardLevelSafe(): void
    {
        $effect = new PendingEffect(
            kind: 'file.read',
            summary: 'Read the agora scrolls',
            arguments: [],
            hazardLevel: 0,
        );

        $result = (new EffectApprovalOverlay($effect))($this->makeContext());

        self::assertStringContainsString('Safe', $this->flattenText($result));
    }

    #[Test]
    public function showsHazardLevelHigh(): void
    {
        $effect = new PendingEffect(
            kind: 'process.run',
            summary: 'Unleash the Spartan phalanx',
            arguments: [],
            hazardLevel: 3,
        );

        $result = (new EffectApprovalOverlay($effect))($this->makeContext());

        self::assertStringContainsString('High', $this->flattenText($result));
    }

    #[Test]
    public function showsActionHints(): void
    {
        $effect = new PendingEffect(
            kind: 'file.write',
            summary: 'Write the marathon dispatch',
            arguments: [],
            hazardLevel: 1,
        );

        $result = (new EffectApprovalOverlay($effect))($this->makeContext());
        $text = $this->flattenText($result);

        self::assertStringContainsString('Approve', $text);
        self::assertStringContainsString('Deny', $text);
    }

    private function makeContext(): RenderContext
    {
        $scope = $this->createStub(TaskScope::class);
        $mountSystem = new MountSystem($scope);

        return new RenderContext($scope, Theme::default(), $mountSystem);
    }

    private function flattenText(mixed $element): string
    {
        if ($element instanceof TextElement) {
            $content = $element->content;
            return is_string($content) ? $content : '';
        }

        if ($element instanceof PanelElement) {
            return $this->flattenText($element->child);
        }

        if ($element instanceof ColumnElement) {
            return implode(' ', array_map($this->flattenText(...), $element->children));
        }

        if ($element instanceof DividerElement) {
            return '';
        }

        return '';
    }
}
