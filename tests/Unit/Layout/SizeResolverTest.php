<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Layout;

use Phalanx\Theatron\Buffer\Rect;
use Phalanx\Theatron\Layout\Size;
use Phalanx\Theatron\Layout\SizeResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SizeResolverTest extends TestCase
{
    #[Test]
    public function fixedSizesAllocateExactly(): void
    {
        $result = SizeResolver::resolve(100, [Size::fixed(30), Size::fixed(70)]);

        self::assertSame([30, 70], $result);
    }

    #[Test]
    public function fillDistributesRemainingSpace(): void
    {
        $result = SizeResolver::resolve(100, [Size::fixed(20), Size::fill(), Size::fixed(10)]);

        self::assertSame([20, 70, 10], $result);
    }

    #[Test]
    public function percentAllocatesProportionally(): void
    {
        $result = SizeResolver::resolve(200, [Size::percent(25), Size::percent(75)]);

        self::assertSame([50, 150], $result);
    }

    #[Test]
    public function verticalProducesCorrectRects(): void
    {
        $area = Rect::sized(80, 24);
        $rects = SizeResolver::vertical($area, [Size::fixed(3), Size::fill(), Size::fixed(1)]);

        self::assertCount(3, $rects);
        self::assertSame(0, $rects[0]->y);
        self::assertSame(3, $rects[0]->height);
        self::assertSame(3, $rects[1]->y);
        self::assertSame(20, $rects[1]->height);
        self::assertSame(23, $rects[2]->y);
        self::assertSame(1, $rects[2]->height);
    }
}
