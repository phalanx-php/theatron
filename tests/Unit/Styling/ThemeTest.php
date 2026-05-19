<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Styling;

use Phalanx\Theatron\Layout\Border;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Style\Modifier;
use Phalanx\Theatron\Style\Style as AnsiStyle;
use Phalanx\Theatron\Styling\Theme;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ThemeTest extends TestCase
{
    #[Test]
    public function surfaceColorsAreSet(): void
    {
        $theme = Theme::default();

        self::assertInstanceOf(Color::class, $theme->fg);
        self::assertInstanceOf(Color::class, $theme->bg);
        self::assertInstanceOf(Color::class, $theme->surface);
        self::assertInstanceOf(Color::class, $theme->border);
        self::assertInstanceOf(Color::class, $theme->highlight);
    }

    #[Test]
    public function textHierarchyStylesAreSet(): void
    {
        $theme = Theme::default();

        self::assertInstanceOf(AnsiStyle::class, $theme->default);
        self::assertInstanceOf(AnsiStyle::class, $theme->muted);
        self::assertInstanceOf(AnsiStyle::class, $theme->subtle);
        self::assertInstanceOf(AnsiStyle::class, $theme->bright);
    }

    #[Test]
    public function brightStyleIsBold(): void
    {
        $theme = Theme::default();

        self::assertTrue($theme->bright->hasModifier(Modifier::Bold));
    }

    #[Test]
    public function semanticAccentsResolve(): void
    {
        $theme = Theme::default();
        $accent = $theme->resolve('accent');

        self::assertNotNull($accent);
        self::assertInstanceOf(AnsiStyle::class, $accent);
        self::assertTrue($accent->equals($theme->accent));
    }

    #[Test]
    public function resolveReturnsNullForUnknown(): void
    {
        $theme = Theme::default();

        self::assertNull($theme->resolve('nonexistent'));
        self::assertNull($theme->resolve('olympus'));
        self::assertNull($theme->resolve(''));
    }

    #[Test]
    public function resolveIsCaseInsensitive(): void
    {
        $theme = Theme::default();

        self::assertNotNull($theme->resolve('ACCENT'));
        self::assertNotNull($theme->resolve('Accent'));
        self::assertNotNull($theme->resolve('SUCCESS'));
        self::assertNotNull($theme->resolve('Bold'));
    }

    #[Test]
    public function resolveModifierReturnsBoldStyle(): void
    {
        $theme = Theme::default();
        $bold = $theme->resolve('bold');

        self::assertNotNull($bold);
        self::assertTrue($bold->hasModifier(Modifier::Bold));
    }

    #[Test]
    public function layoutPanelHasSingleBorder(): void
    {
        $theme = Theme::default();

        self::assertSame(Border::Single, $theme->panel->border);
    }

    #[Test]
    public function layoutInputHasRoundedBorder(): void
    {
        $theme = Theme::default();

        self::assertSame(Border::Rounded, $theme->input->border);
    }

    #[Test]
    public function activeStyleHasBackground(): void
    {
        $theme = Theme::default();

        self::assertNotNull($theme->active->background);
    }

    #[Test]
    public function defaultThemePinsKnownValues(): void
    {
        $theme = Theme::default();

        self::assertTrue($theme->fg->equals(Color::hex('#e0e0e0')));
        self::assertNotNull($theme->accent->foreground);
        self::assertTrue($theme->accent->foreground->equals(Color::hex('#88ccff')));
        self::assertNotNull($theme->error->foreground);
        self::assertTrue($theme->error->foreground->equals(Color::hex('#cc6666')));
    }

    #[Test]
    public function resolveReturnsSameInstanceForModifiers(): void
    {
        $theme = Theme::default();

        $first = $theme->resolve('bold');
        $second = $theme->resolve('bold');

        self::assertSame($first, $second);
    }
}
