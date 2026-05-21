<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Tdom;

use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\MountElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\RowElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Text\Line;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function Phalanx\Theatron\Ui\column;
use function Phalanx\Theatron\Ui\mount;
use function Phalanx\Theatron\Ui\panel;
use function Phalanx\Theatron\Ui\row;
use function Phalanx\Theatron\Ui\text;

final class FunctionBuilderTest extends TestCase
{
    #[Test]
    public function textBuildsTextElementLikeUiObject(): void
    {
        $style = Style::empty();
        $function = text('Apollo', $style);
        $object = new Ui()->text('Apollo', $style);

        self::assertInstanceOf(TextElement::class, $function);
        self::assertSame($object->content, $function->content);
        self::assertSame($object->style, $function->style);
    }

    #[Test]
    public function textAcceptsLineContent(): void
    {
        $line = Line::plain('Athena');

        $element = text($line);

        self::assertSame($line, $element->content);
    }

    #[Test]
    public function panelBuildsPanelElementLikeUiObject(): void
    {
        $child = text('child');
        $style = Style::empty();
        $function = panel('Title', $child, $style);
        $object = new Ui()->panel('Title', $child, $style);

        self::assertInstanceOf(PanelElement::class, $function);
        self::assertSame($object->title, $function->title);
        self::assertSame($object->child, $function->child);
        self::assertSame($object->style, $function->style);
    }

    #[Test]
    public function columnBuildsColumnElementLikeUiObject(): void
    {
        $child = text('child');
        $function = column($child);
        $object = new Ui()->column($child);

        self::assertInstanceOf(ColumnElement::class, $function);
        self::assertSame($object->children, $function->children);
    }

    #[Test]
    public function rowBuildsRowElementLikeUiObject(): void
    {
        $child = text('child');
        $function = row($child);
        $object = new Ui()->row($child);

        self::assertInstanceOf(RowElement::class, $function);
        self::assertSame($object->children, $function->children);
    }

    #[Test]
    public function textDoesNotParseMarkupWithoutThemeContext(): void
    {
        $element = text('[bold]Sparta[/]');

        self::assertSame('[bold]Sparta[/]', $element->content);
    }

    #[Test]
    public function mountStoresComponentClassAndNamedProps(): void
    {
        $element = mount(FunctionBuilderComponent::class, label: 'Hermes');

        self::assertInstanceOf(MountElement::class, $element);
        self::assertSame(FunctionBuilderComponent::class, $element->component);
        self::assertSame(['label' => 'Hermes'], $element->props);
    }

    #[Test]
    public function mountRejectsPositionalProps(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Component props must be passed as named arguments.');

        mount(FunctionBuilderComponent::class, 'Hermes');
    }
}

final class FunctionBuilderComponent implements Component
{
    public function __construct(
        private string $label = 'default',
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text($this->label);
    }
}
