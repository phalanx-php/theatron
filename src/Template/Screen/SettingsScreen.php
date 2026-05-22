<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Screen;

use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\DeclaresBindings;
use Phalanx\Theatron\Contract\Focusable;
use Phalanx\Theatron\Contract\HasFocusables;
use Phalanx\Theatron\Contract\HasStatusBar;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Input\NormalModeHandler;
use Phalanx\Theatron\Layout\Size;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Style\Style as TextStyle;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style as TdomStyle;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\SettingsTab;
use Phalanx\Theatron\Text\Line;
use Phalanx\Theatron\Text\Span;

class SettingsScreen implements Screen, HasStatusBar, HasFocusables, DeclaresBindings, NormalModeHandler
{
    public function __construct(
        private(set) AppStore $store,
    ) {
    }

    public function __invoke(ScreenContext $ctx): Renderable
    {
        $settings = $this->store->settings;
        $modelName = $this->store->activity->modelName;
        $rows = [
            self::row($ctx->ui, Line::from(Span::styled('  Settings', self::headerStyle()))),
            self::divider($ctx->ui, 100),
            self::row($ctx->ui, $this->tabs()),
            self::divider($ctx->ui, 100),
            self::blank($ctx->ui),
        ];

        foreach ($settings->activeTab->items($modelName) as $i => $item) {
            $rows[] = self::row(
                $ctx->ui,
                $this->item(
                    $item[0],
                    $item[1],
                    $i === $settings->selectedItem,
                    $settings->isEnabled($settings->activeTab, $i, $modelName),
                ),
            );
        }

        $rows[] = self::blank($ctx->ui);
        $rows[] = self::divider($ctx->ui, 100);

        return $ctx->ui->column(...$rows);
    }

    public function statusBar(Ui $ui): Renderable
    {
        return $ui->text(
            Line::from(
                Span::styled('  ←', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' tab', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('→', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' tab', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('↑/↓', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' item', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('Space', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' toggle', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('^C', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' quit', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('Esc', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' back', TextStyle::new()->fg(Color::indexed(250))),
            ),
            TdomStyle::of(size: Size::fixed(1)),
        );
    }

    /** @return list<array{string, Focusable}> */
    public function focusables(): array
    {
        return [['settings', $this]];
    }

    /** @return list<Binding> */
    public function bindings(): array
    {
        return [
            Binding::key(Key::Left)->label('tab'),
            Binding::key(Key::Right)->label('tab'),
            Binding::key(Key::Up)->label('item'),
            Binding::key(Key::Down)->label('item'),
            Binding::key(Key::Space)->label('toggle'),
            Binding::key(Key::Enter)->label('toggle'),
            Binding::key(Key::Escape)->back()->label('back'),
        ];
    }

    public function handleNormalKey(KeyEvent $event): bool
    {
        $modelName = $this->store->activity->modelName;

        if ($event->is(Key::Left)) {
            $this->store->settings = $this->store->settings->prevTab();

            return true;
        }

        if ($event->is(Key::Right)) {
            $this->store->settings = $this->store->settings->nextTab();

            return true;
        }

        if ($event->is(Key::Up)) {
            $this->store->settings = $this->store->settings->prevItem();

            return true;
        }

        if ($event->is(Key::Down)) {
            $this->store->settings = $this->store->settings->nextItem($modelName);

            return true;
        }

        if ($event->is(Key::Space) || $event->is(Key::Enter)) {
            $this->store->settings = $this->store->settings->toggleSelected($modelName);

            return true;
        }

        return false;
    }

    private static function row(Ui $ui, Line $line): Renderable
    {
        return $ui->text($line, TdomStyle::of(size: Size::fixed(1)));
    }

    private static function blank(Ui $ui): Renderable
    {
        return self::row($ui, Line::plain(''));
    }

    private static function divider(Ui $ui, int $width): Renderable
    {
        return self::row($ui, Line::from(
            Span::styled(str_repeat('─', $width), TextStyle::new()->fg(Color::indexed(236))),
        ));
    }

    private static function pipe(): Span
    {
        return Span::styled('  │  ', TextStyle::new()->fg(Color::indexed(238)));
    }

    private static function headerStyle(): TextStyle
    {
        return TextStyle::new()->fg(Color::indexed(255))->bold();
    }

    private function tabs(): Line
    {
        $spans = [Span::plain('  ')];

        foreach (SettingsTab::cases() as $tab) {
            $style = $tab === $this->store->settings->activeTab
                ? TextStyle::new()->fg(Color::indexed(255))->bold()->underline()
                : TextStyle::new()->fg(Color::indexed(245));
            $spans[] = Span::styled(' ' . $tab->value . ' ', $style);
        }

        return Line::from(...$spans);
    }

    private function item(string $label, string $type, bool $selected, bool $enabled): Line
    {
        $prefix = $selected ? '  > ' : '    ';
        $text = $type === 'toggle'
            ? $prefix . ($enabled ? '[x] ' : '[ ] ') . $label
            : $prefix . $label;
        $style = $selected
            ? TextStyle::new()->fg(Color::indexed(255))->bold()
            : TextStyle::new()->fg(Color::indexed(250));

        return Line::from(Span::styled($text, $style));
    }
}
