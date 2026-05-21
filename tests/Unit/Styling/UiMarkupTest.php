<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Styling;

use Phalanx\Theatron\Style\Style as AnsiStyle;
use Phalanx\Theatron\Styling\BBCode;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Text\Line;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UiMarkupTest extends TestCase
{
    #[Test]
    public function panelTitleWithThemeParsesMarkup(): void
    {
        $ui = new Ui(Theme::default());
        $panel = $ui->panel('[accent]Apollo[/]', $ui->text('body'));

        self::assertInstanceOf(Line::class, $panel->title);
    }

    #[Test]
    public function panelTitleWithoutThemeStaysString(): void
    {
        $ui = new Ui();
        $panel = $ui->panel('[accent]Apollo[/]', $ui->text('body'));

        self::assertIsString($panel->title);
    }

    #[Test]
    public function panelTitleWithoutBracketsStaysString(): void
    {
        $ui = new Ui(Theme::default());
        $panel = $ui->panel('Dashboard', $ui->text('body'));

        self::assertIsString($panel->title);
        self::assertSame('Dashboard', $panel->title);
    }

    #[Test]
    public function spinnerLabelWithThemeParsesMarkup(): void
    {
        $ui = new Ui(Theme::default());
        $spinner = $ui->spinner('[success]Loading[/]');

        self::assertInstanceOf(Line::class, $spinner->label);
    }

    #[Test]
    public function spinnerLabelWithoutThemeStaysString(): void
    {
        $ui = new Ui();
        $spinner = $ui->spinner('[success]Loading[/]');

        self::assertIsString($spinner->label);
    }

    #[Test]
    public function spinnerNullLabelStaysNull(): void
    {
        $ui = new Ui(Theme::default());
        $spinner = $ui->spinner();

        self::assertNull($spinner->label);
    }

    #[Test]
    public function inputPromptWithThemeParsesMarkup(): void
    {
        $ui = new Ui(Theme::default());
        $input = $ui->input(prompt: '[accent]>[/] ');

        self::assertInstanceOf(Line::class, $input->prompt);
    }

    #[Test]
    public function inputPromptWithoutThemeStaysString(): void
    {
        $ui = new Ui();
        $input = $ui->input(prompt: '[accent]>[/] ');

        self::assertIsString($input->prompt);
    }

    #[Test]
    public function inputPromptWithoutBracketsStaysString(): void
    {
        $ui = new Ui(Theme::default());
        $input = $ui->input(prompt: '> ');

        self::assertIsString($input->prompt);
        self::assertSame('> ', $input->prompt);
    }

    #[Test]
    public function progressLabelWithThemeParsesMarkup(): void
    {
        $ui = new Ui(Theme::default());
        $progress = $ui->progress(0.5, '[bold]CPU[/]');

        self::assertInstanceOf(Line::class, $progress->label);
    }

    #[Test]
    public function progressLabelWithoutThemeStaysString(): void
    {
        $ui = new Ui();
        $progress = $ui->progress(0.5, '[bold]CPU[/]');

        self::assertIsString($progress->label);
    }

    #[Test]
    public function progressNullLabelStaysNull(): void
    {
        $ui = new Ui(Theme::default());
        $progress = $ui->progress(0.5);

        self::assertNull($progress->label);
    }

    #[Test]
    public function bbcodeResolvesCustomTagFromWithTags(): void
    {
        $agentStyle = AnsiStyle::new()->fg('#77cc77')->bold();
        $theme = Theme::default()->withTags(['agent' => $agentStyle]);

        $line = BBCode::parse('[agent]Leonidas[/]', $theme);

        self::assertCount(1, $line->spans);
        self::assertTrue($line->spans[0]->style->equals($agentStyle));
        self::assertSame('Leonidas', $line->spans[0]->content);
    }

    #[Test]
    public function uiTextResolvesCustomTagEndToEnd(): void
    {
        $agentStyle = AnsiStyle::new()->fg('#ff6666');
        $theme = Theme::default()->withTags(['danger' => $agentStyle]);
        $ui = new Ui($theme);

        $text = $ui->text('[danger]Alert[/]');
        $content = $text->content;

        self::assertInstanceOf(Line::class, $content);
        self::assertSame('Alert', $content->spans[0]->content);
    }
}
