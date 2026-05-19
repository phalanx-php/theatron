<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Tdom\Painter;

use Phalanx\Theatron\Buffer\Buffer;
use Phalanx\Theatron\Buffer\Rect;
use Phalanx\Theatron\Component\MountedComponent;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Component\SignalScanner;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Layout\Border;
use Phalanx\Theatron\Layout\Padding;
use Phalanx\Theatron\Layout\Size;
use Phalanx\Theatron\Reactive\DirtyBatch;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Style\Modifier;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\DividerElement;
use Phalanx\Theatron\Tdom\Element\GridElement;
use Phalanx\Theatron\Tdom\Element\InputElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\ProgressElement;
use Phalanx\Theatron\Tdom\Element\RowElement;
use Phalanx\Theatron\Tdom\Element\ScrollElement;
use Phalanx\Theatron\Tdom\Element\SpinnerElement;
use Phalanx\Theatron\Tdom\Element\StatusLineElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Painter\PaintContext;
use Phalanx\Theatron\Tdom\Painter\Painter;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style;
use Phalanx\Theatron\Tdom\Ui;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PainterTest extends TestCase
{
    #[Test]
    public function textPaintsStringIntoBuffer(): void
    {
        $buf = Buffer::empty(20, 3);
        $ctx = new PaintContext(Rect::sized(20, 3), $buf);

        Painter::paint(new TextElement('Hello'), $ctx);

        self::assertSame('H', $buf->get(0, 0)->char);
        self::assertSame('e', $buf->get(1, 0)->char);
        self::assertSame('l', $buf->get(2, 0)->char);
        self::assertSame('l', $buf->get(3, 0)->char);
        self::assertSame('o', $buf->get(4, 0)->char);
    }

    #[Test]
    public function panelPaintsBorderAndTitle(): void
    {
        $buf = Buffer::empty(20, 5);
        $ctx = new PaintContext(Rect::sized(20, 5), $buf);

        $panel = new PanelElement(
            'Test',
            new TextElement('inner'),
            Style::of(border: Border::Single),
        );

        Painter::paint($panel, $ctx);

        [$tl, $tr, $bl, $br, $h, $v] = Border::Single->chars();
        self::assertSame($tl, $buf->get(0, 0)->char);
        self::assertSame($tr, $buf->get(19, 0)->char);
        self::assertSame($bl, $buf->get(0, 4)->char);
        self::assertSame($br, $buf->get(19, 4)->char);
        self::assertSame($v, $buf->get(0, 1)->char);
        self::assertSame($h, $buf->get(1, 4)->char);
    }

    #[Test]
    public function panelRendersChildInside(): void
    {
        $buf = Buffer::empty(20, 5);
        $ctx = new PaintContext(Rect::sized(20, 5), $buf);

        $panel = new PanelElement(
            '',
            new TextElement('X'),
            Style::of(border: Border::Single),
        );

        Painter::paint($panel, $ctx);

        self::assertSame('X', $buf->get(1, 1)->char);
    }

    #[Test]
    public function columnStacksChildrenVertically(): void
    {
        $buf = Buffer::empty(10, 4);
        $ctx = new PaintContext(Rect::sized(10, 4), $buf);

        $col = new ColumnElement([
            new TextElement('Top', Style::of(size: Size::fixed(1))),
            new TextElement('Bot', Style::of(size: Size::fixed(1))),
        ]);

        Painter::paint($col, $ctx);

        self::assertSame('T', $buf->get(0, 0)->char);
        self::assertSame('B', $buf->get(0, 1)->char);
    }

    #[Test]
    public function rowDistributesChildrenHorizontally(): void
    {
        $buf = Buffer::empty(20, 1);
        $ctx = new PaintContext(Rect::sized(20, 1), $buf);

        $row = new RowElement([
            new TextElement('L', Style::of(size: Size::fixed(5))),
            new TextElement('R', Style::of(size: Size::fixed(5))),
        ]);

        Painter::paint($row, $ctx);

        self::assertSame('L', $buf->get(0, 0)->char);
        self::assertSame('R', $buf->get(5, 0)->char);
    }

    #[Test]
    public function dividerPaintsHorizontalLine(): void
    {
        $buf = Buffer::empty(10, 1);
        $ctx = new PaintContext(Rect::sized(10, 1), $buf);

        Painter::paint(new DividerElement(), $ctx);

        for ($x = 0; $x < 10; $x++) {
            self::assertSame('─', $buf->get($x, 0)->char);
        }
    }

    #[Test]
    public function progressPaintsFilledAndEmpty(): void
    {
        $buf = Buffer::empty(25, 1);
        $ctx = new PaintContext(Rect::sized(25, 1), $buf);

        Painter::paint(new ProgressElement(0.5), $ctx);

        $filled = 0;
        $empty = 0;

        for ($x = 0; $x < 20; $x++) {
            $ch = $buf->get($x, 0)->char;
            if ($ch === '█') {
                $filled++;
            } elseif ($ch === '░') {
                $empty++;
            }
        }

        self::assertGreaterThan(0, $filled);
        self::assertGreaterThan(0, $empty);
    }

    #[Test]
    public function paintContextSubSharesBuffer(): void
    {
        $buf = Buffer::empty(20, 10);
        $ctx = new PaintContext(Rect::sized(20, 10), $buf);

        $sub = $ctx->sub(Rect::of(5, 5, 10, 5));

        self::assertSame($buf, $sub->buffer);
        self::assertSame(5, $sub->area->x);
        self::assertSame(5, $sub->area->y);
    }

    #[Test]
    public function resolveAnsiStyleConvertsColors(): void
    {
        $tdomStyle = Style::of(color: Color::red(), background: Color::blue());
        $ansi = Painter::resolveAnsiStyle($tdomStyle);

        self::assertNotNull($ansi->foreground);
        self::assertTrue($ansi->foreground->equals(Color::red()));
        self::assertNotNull($ansi->background);
        self::assertTrue($ansi->background->equals(Color::blue()));
    }

    #[Test]
    public function resolveAnsiStyleReturnsEmptyForNull(): void
    {
        $ansi = Painter::resolveAnsiStyle(null);
        self::assertNull($ansi->foreground);
        self::assertNull($ansi->background);
    }

    #[Test]
    public function paddingAdjustsRenderArea(): void
    {
        $buf = Buffer::empty(20, 5);
        $ctx = new PaintContext(Rect::sized(20, 5), $buf);

        $text = new TextElement(
            'padded',
            Style::of(padding: Padding::all(1)),
        );

        Painter::paint($text, $ctx);

        self::assertSame(' ', $buf->get(0, 0)->char);
        self::assertSame('p', $buf->get(1, 1)->char);
    }

    #[Test]
    public function zeroSizeAreaSkipsPainting(): void
    {
        $buf = Buffer::empty(5, 5);
        $ctx = new PaintContext(Rect::sized(0, 0), $buf);

        Painter::paint(new TextElement('nope'), $ctx);

        self::assertSame(' ', $buf->get(0, 0)->char);
    }

    #[Test]
    public function statusLinePaintsUnstyled(): void
    {
        $buf = Buffer::empty(20, 1);
        $ctx = new PaintContext(Rect::sized(20, 1), $buf);

        $sl = new StatusLineElement([
            new TextElement('left'),
            new TextElement('right'),
        ]);

        Painter::paint($sl, $ctx);

        self::assertSame('l', $buf->get(0, 0)->char);
        self::assertSame('r', $buf->get(10, 0)->char);
    }

    #[Test]
    public function gridPaintsCellsInGrid(): void
    {
        $buf = Buffer::empty(20, 4);
        $ctx = new PaintContext(Rect::sized(20, 4), $buf);

        $grid = new GridElement(
            [Size::fixed(10), Size::fixed(10)],
            [
                new TextElement('A'),
                new TextElement('B'),
                new TextElement('C'),
                new TextElement('D'),
            ],
        );

        Painter::paint($grid, $ctx);

        self::assertSame('A', $buf->get(0, 0)->char);
        self::assertSame('B', $buf->get(10, 0)->char);
        self::assertSame('C', $buf->get(0, 2)->char);
        self::assertSame('D', $buf->get(10, 2)->char);
    }

    #[Test]
    public function scrollPaintsVisibleLines(): void
    {
        $buf = Buffer::empty(20, 2);
        $ctx = new PaintContext(Rect::sized(20, 2), $buf);

        Painter::paint(new ScrollElement("line1\nline2\nline3", 2), $ctx);

        self::assertSame('l', $buf->get(0, 0)->char);
        self::assertSame('2', $buf->get(4, 0)->char, 'First visible line should be "line2"');
        self::assertSame('l', $buf->get(0, 1)->char);
        self::assertSame('3', $buf->get(4, 1)->char, 'Second visible line should be "line3"');
    }

    #[Test]
    public function inputPaintsPromptValueAndCursor(): void
    {
        $buf = Buffer::empty(20, 1);
        $ctx = new PaintContext(Rect::sized(20, 1), $buf);

        Painter::paint(new InputElement('hello', '> ', 3), $ctx);

        self::assertSame('>', $buf->get(0, 0)->char);
        self::assertSame(' ', $buf->get(1, 0)->char);
        self::assertSame('h', $buf->get(2, 0)->char);
        self::assertSame('e', $buf->get(3, 0)->char);

        $cursorCell = $buf->get(5, 0);
        self::assertSame('l', $cursorCell->char);
        self::assertTrue($cursorCell->style->hasModifier(Modifier::Reverse));
    }

    #[Test]
    public function spinnerPaintsFrameAndLabel(): void
    {
        $buf = Buffer::empty(20, 1);
        $ctx = new PaintContext(Rect::sized(20, 1), $buf);

        Painter::paint(new SpinnerElement('Wait', 0), $ctx);

        self::assertNotSame(' ', $buf->get(0, 0)->char);
        self::assertSame('W', $buf->get(2, 0)->char);
        self::assertSame('a', $buf->get(3, 0)->char);
    }

    #[Test]
    public function progressClampsOutOfRangeValues(): void
    {
        $buf = Buffer::empty(25, 1);
        $ctx = new PaintContext(Rect::sized(25, 1), $buf);

        Painter::paint(new ProgressElement(1.5), $ctx);

        for ($x = 0; $x < 20; $x++) {
            self::assertSame('█', $buf->get($x, 0)->char, "Cell {$x} should be filled at 150%");
        }
    }

    #[Test]
    public function columnWithUnstyledChildren(): void
    {
        $buf = Buffer::empty(10, 4);
        $ctx = new PaintContext(Rect::sized(10, 4), $buf);

        $col = new ColumnElement([
            new TextElement('A'),
            new TextElement('B'),
        ]);

        Painter::paint($col, $ctx);

        self::assertSame('A', $buf->get(0, 0)->char);
        self::assertSame('B', $buf->get(0, 2)->char);
    }

    #[Test]
    public function paintDirtyMountedComponentRerenders(): void
    {
        $tracker = new \stdClass();
        $tracker->count = 0;

        $component = new class ($tracker) implements Component {
            public function __construct(private \stdClass $tracker)
            {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                $this->tracker->count++;

                return $ctx->ui->text('rendered');
            }
        };

        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch);
        $mounted = new MountedComponent($component, $batch, $scanResult);

        $scope = $this->createStub(\Phalanx\Scope\Scope::class);
        $mountSystem = new MountSystem($scope);
        $ctx = new RenderContext($scope, new Ui(), Theme::default(), $mountSystem);

        $mounted->render($ctx);
        self::assertSame(1, $tracker->count);

        $mounted->markDirty();

        $buf = Buffer::empty(20, 1);
        $paintCtx = new PaintContext(Rect::sized(20, 1), $buf);

        Painter::paint($mounted, $paintCtx);

        self::assertSame(2, $tracker->count, 'Dirty MountedComponent must rerender during paint');
        self::assertSame('r', $buf->get(0, 0)->char);
    }

    #[Test]
    public function paintCleanMountedComponentSkipsRerender(): void
    {
        $tracker = new \stdClass();
        $tracker->count = 0;

        $component = new class ($tracker) implements Component {
            public function __construct(private \stdClass $tracker)
            {
            }

            public function __invoke(RenderContext $ctx): Renderable
            {
                $this->tracker->count++;

                return $ctx->ui->text('cached');
            }
        };

        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch);
        $mounted = new MountedComponent($component, $batch, $scanResult);

        $scope = $this->createStub(\Phalanx\Scope\Scope::class);
        $mountSystem = new MountSystem($scope);
        $ctx = new RenderContext($scope, new Ui(), Theme::default(), $mountSystem);

        $mounted->render($ctx);
        self::assertSame(1, $tracker->count);

        $buf = Buffer::empty(20, 1);
        $paintCtx = new PaintContext(Rect::sized(20, 1), $buf);

        Painter::paint($mounted, $paintCtx);

        self::assertSame(1, $tracker->count, 'Clean MountedComponent must not rerender');
        self::assertSame('c', $buf->get(0, 0)->char);
    }

    #[Test]
    public function paintMountedComponentWithNullLastResultIsNoOp(): void
    {
        $component = new class () implements Component {
            public function __invoke(RenderContext $ctx): Renderable
            {
                return $ctx->ui->text('never');
            }
        };

        $batch = new DirtyBatch();
        $scanResult = SignalScanner::scan($component, $batch);
        $mounted = new MountedComponent($component, $batch, $scanResult);

        $buf = Buffer::empty(20, 1);
        $paintCtx = new PaintContext(Rect::sized(20, 1), $buf);

        $mounted->dispose();

        Painter::paint($mounted, $paintCtx);

        self::assertSame(' ', $buf->get(0, 0)->char, 'Disposed component with null lastResult must not crash');
    }

    protected function tearDown(): void
    {
        Painter::reset();
    }
}
