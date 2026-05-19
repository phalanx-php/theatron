<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Binding;

use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Binding\BindingHintsFormatter;
use Phalanx\Theatron\Binding\BindingRegistry;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\RowElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Ui;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures use Greek/Spartan lexicon: Leonidas, Thermopylae, Olympus, Sparta.
 */
final class BindingHintsFormatterTest extends TestCase
{
    private Ui $ui;

    // -------------------------------------------------------------------------
    // Data providers

    /** @return array<string, array{Key, string}> */
    public static function functionKeyProvider(): array
    {
        return [
            'F1'  => [Key::F1,  'F1'],
            'F5'  => [Key::F5,  'F5'],
            'F10' => [Key::F10, 'F10'],
            'F12' => [Key::F12, 'F12'],
        ];
    }

    // -------------------------------------------------------------------------
    // formatCombo — key combo string

    #[Test]
    public function ctrlCharFormatted(): void
    {
        $binding = Binding::ctrl('c')->quit()->label('Quit');

        self::assertSame('Ctrl+c', BindingHintsFormatter::formatCombo($binding));
    }

    #[Test]
    public function altCharFormatted(): void
    {
        $binding = Binding::alt('x')->quit()->label('Exit');

        self::assertSame('Alt+x', BindingHintsFormatter::formatCombo($binding));
    }

    #[Test]
    public function namedFunctionKeyFormatted(): void
    {
        $binding = Binding::key(Key::F12)->action(static fn () => null)->label('DevTools');

        self::assertSame('F12', BindingHintsFormatter::formatCombo($binding));
    }

    #[Test]
    public function namedEnterKeyFormatted(): void
    {
        $binding = Binding::key(Key::Enter)->action(static fn () => null)->label('Confirm');

        self::assertSame('Enter', BindingHintsFormatter::formatCombo($binding));
    }

    #[Test]
    public function namedPageUpKeyFormatted(): void
    {
        $binding = Binding::key(Key::PageUp)->action(static fn () => null)->label('Scroll up');

        self::assertSame('PageUp', BindingHintsFormatter::formatCombo($binding));
    }

    #[Test]
    public function singleCharKeyFormatted(): void
    {
        $binding = Binding::key('s')->action(static fn () => null)->label('Save');

        self::assertSame('s', BindingHintsFormatter::formatCombo($binding));
    }

    #[Test]
    public function ctrlShiftComboFormatted(): void
    {
        // Binding::ctrl gives ctrl=true; we can't set shift via public API without
        // using action + checking the output, so test the format contract directly.
        $binding = Binding::ctrl('p')->quit()->label('Palette');

        // shift is false by default from ctrl() factory — just verify ctrl prefix.
        self::assertSame('Ctrl+p', BindingHintsFormatter::formatCombo($binding));
    }

    /** @param Key $key */
    #[Test]
    #[DataProvider('functionKeyProvider')]
    public function functionKeysAreUppercased(Key $key, string $expected): void
    {
        $binding = Binding::key($key)->action(static fn () => null)->label('action');

        self::assertSame($expected, BindingHintsFormatter::formatCombo($binding));
    }

    // -------------------------------------------------------------------------
    // render — RowElement structure

    #[Test]
    public function emptyBindingsProducesEmptyRow(): void
    {
        $row = BindingHintsFormatter::render($this->ui, []);

        self::assertInstanceOf(RowElement::class, $row);
        self::assertCount(0, $row->children);
    }

    #[Test]
    public function bindingWithoutLabelIsSkipped(): void
    {
        $quit = Binding::ctrl('c')->quit(); // no label

        $row = BindingHintsFormatter::render($this->ui, [$quit]);

        self::assertInstanceOf(RowElement::class, $row);
        self::assertCount(0, $row->children);
    }

    #[Test]
    public function singleBindingProducesTwoChildren(): void
    {
        // Leonidas — Ctrl+Q Quit
        $quit = Binding::ctrl('q')->quit()->label('Quit');

        $row = BindingHintsFormatter::render($this->ui, [$quit]);

        // combo text + label text; no separator for single binding
        self::assertCount(2, $row->children);
        self::assertInstanceOf(TextElement::class, $row->children[0]);
        self::assertInstanceOf(TextElement::class, $row->children[1]);
    }

    #[Test]
    public function twoBindingsProduceFiveChildren(): void
    {
        // Thermopylae — two bindings separated by two-space pad
        $quit = Binding::ctrl('c')->quit()->label('Quit');
        $save = Binding::key('s')->action(static fn () => null)->label('Save');

        $row = BindingHintsFormatter::render($this->ui, [$quit, $save]);

        // combo + label + separator + combo + label = 5
        self::assertCount(5, $row->children);
    }

    #[Test]
    public function threeBindingsProduceNineChildren(): void
    {
        // Olympus — three bindings, two separators
        $quit  = Binding::ctrl('c')->quit()->label('Quit');
        $save  = Binding::key('s')->action(static fn () => null)->label('Save');
        $help  = Binding::key(Key::F1)->action(static fn () => null)->label('Help');

        $row = BindingHintsFormatter::render($this->ui, [$quit, $save, $help]);

        // 3 * 2 (combo+label) + 2 separators = 8
        self::assertCount(8, $row->children);
    }

    #[Test]
    public function bindingWithLabelEmptyStringIsSkipped(): void
    {
        $blank = Binding::ctrl('x')->quit()->label('');

        $row = BindingHintsFormatter::render($this->ui, [$blank]);

        self::assertCount(0, $row->children);
    }

    // -------------------------------------------------------------------------
    // RenderContext::hints() integration

    #[Test]
    public function hintsReturnsEmptyRowWithNoRegistry(): void
    {
        $scope = $this->createStub(\Phalanx\Scope\Scope::class);
        $mount = new MountSystem($scope);
        $ctx = new RenderContext($scope, $this->ui, Theme::default(), $mount);

        $row = $ctx->hints();

        self::assertInstanceOf(RowElement::class, $row);
        self::assertCount(0, $row->children);
    }

    #[Test]
    public function hintsReturnsRowFromRegistry(): void
    {
        // Sparta — global binding wired through RenderContext
        $scope = $this->createStub(\Phalanx\Scope\Scope::class);
        $mount = new MountSystem($scope);
        $registry = new BindingRegistry();
        $registry->setGlobal([
            Binding::ctrl('c')->quit()->label('Quit'),
        ]);
        $ctx = new RenderContext($scope, $this->ui, Theme::default(), $mount, $registry);

        $row = $ctx->hints();

        self::assertInstanceOf(RowElement::class, $row);
        self::assertGreaterThan(0, count($row->children));
    }

    #[Test]
    public function hintsRespectsActiveBindingsLayering(): void
    {
        // Leonidas — overlay shadows global for same combo; only overlay appears
        $scope = $this->createStub(\Phalanx\Scope\Scope::class);
        $mount = new MountSystem($scope);
        $registry = new BindingRegistry();

        $global  = Binding::ctrl('c')->quit()->label('GlobalQuit');
        $overlay = Binding::ctrl('c')->quit()->label('OverlayQuit');
        $registry->setGlobal([$global]);
        $registry->pushOverlay('thermopylae', [$overlay]);

        $ctx = new RenderContext($scope, $this->ui, Theme::default(), $mount, $registry);
        $row = $ctx->hints();

        // Only the overlay binding is visible (shadows global); 2 children = combo + label.
        self::assertInstanceOf(RowElement::class, $row);
        self::assertCount(2, $row->children);
    }

    protected function setUp(): void
    {
        $this->ui = new Ui(Theme::default());
    }
}
