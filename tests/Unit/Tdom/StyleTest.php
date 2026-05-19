<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Tdom;

use Phalanx\Theatron\Layout\Align;
use Phalanx\Theatron\Layout\Border;
use Phalanx\Theatron\Layout\Padding;
use Phalanx\Theatron\Layout\Size;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Tdom\Style;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StyleTest extends TestCase
{
    #[Test]
    public function ofWithAllNulls(): void
    {
        $style = Style::of();
        self::assertNull($style->size);
        self::assertNull($style->align);
        self::assertNull($style->border);
        self::assertNull($style->padding);
        self::assertNull($style->color);
        self::assertNull($style->background);
    }

    #[Test]
    public function ofWithAllValues(): void
    {
        $style = Style::of(
            size: Size::fixed(20),
            align: Align::Center,
            border: Border::Single,
            padding: Padding::all(1),
            color: Color::red(),
            background: Color::blue(),
        );

        self::assertNotNull($style->size);
        self::assertSame(Align::Center, $style->align);
        self::assertSame(Border::Single, $style->border);
        self::assertNotNull($style->padding);
        self::assertNotNull($style->color);
        self::assertTrue($style->color->equals(Color::red()));
        self::assertNotNull($style->background);
        self::assertTrue($style->background->equals(Color::blue()));
    }

    #[Test]
    public function ofWithPartialValues(): void
    {
        $style = Style::of(color: Color::green());
        self::assertNull($style->size);
        self::assertNull($style->border);
        self::assertNotNull($style->color);
        self::assertTrue($style->color->equals(Color::green()));
    }
}
