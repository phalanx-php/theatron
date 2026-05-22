<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Screen;

use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Component\MountSystem;
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
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Style\Style as TextStyle;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style as TdomStyle;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\LlmRequestEntry;
use Phalanx\Theatron\Text\Line;
use Phalanx\Theatron\Text\Span;

class DevToolsScreen implements Screen, HasStatusBar, HasFocusables, DeclaresBindings, NormalModeHandler
{
    public function __construct(
        private(set) AppStore $store,
        private(set) MountSystem $mountSystem,
        private(set) Navigator $navigator,
    ) {
    }

    public function __invoke(ScreenContext $ctx): Renderable
    {
        return $ctx->ui->row(
            $ctx->ui->column(...$this->leftRows($ctx->ui)),
            $ctx->ui->column(...$this->requestRows($ctx->ui)),
        );
    }

    public function statusBar(Ui $ui): Renderable
    {
        return $ui->text(
            Line::from(
                Span::styled('  ↑', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' req', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('↓', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' req', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('Enter', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' detail', TextStyle::new()->fg(Color::indexed(250))),
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
        return [['requests', $this]];
    }

    /** @return list<Binding> */
    public function bindings(): array
    {
        return [
            Binding::key(Key::Up)->label('req'),
            Binding::key(Key::Down)->label('req'),
            Binding::key(Key::Enter)->label('detail'),
            Binding::key(Key::Escape)->back()->label('back'),
        ];
    }

    public function handleNormalKey(KeyEvent $event): bool
    {
        if ($event->is(Key::Up)) {
            $this->store->requests = $this->store->requests->focusUp();

            return true;
        }

        if ($event->is(Key::Down)) {
            $this->store->requests = $this->store->requests->focusDown();

            return true;
        }

        if ($event->is(Key::Enter) && $this->store->requests->focused() !== null) {
            $this->store->requests = $this->store->requests->resetDetailScroll();
            $this->navigator->go(LlmRequestDetailScreen::class);

            return true;
        }

        return false;
    }

    private static function requestRow(Ui $ui, LlmRequestEntry $entry, bool $focused): Renderable
    {
        $status = match (true) {
            $entry->error !== null => 'ERR',
            $entry->status !== null => (string) $entry->status,
            default => '...',
        };
        $time = $entry->elapsedMs !== null ? number_format($entry->elapsedMs / 1000, 1) . 's' : '...';
        $tokens = $entry->tokenCount !== null ? $entry->tokenCount . ' tok' : '';

        return self::row($ui, Line::from(
            Span::styled(
                $focused ? ' ▶ ' : '   ',
                TextStyle::new()->fg($focused ? Color::indexed(252) : Color::indexed(242)),
            ),
            Span::styled($entry->method . ' ' . $entry->path . '  ', TextStyle::new()->fg(Color::indexed(252))->bold()),
            Span::styled(trim($status . ' • ' . $time . ' • ' . $tokens), TextStyle::new()->fg(Color::indexed(245))),
        ));
    }

    private static function kv(Ui $ui, string $key, string $value): Renderable
    {
        return self::row($ui, Line::from(
            Span::styled("    {$key}: ", TextStyle::new()->fg(Color::indexed(245))),
            Span::styled($value, TextStyle::new()->fg(Color::indexed(250))),
        ));
    }

    private static function row(Ui $ui, Line $line): Renderable
    {
        return $ui->text($line, TdomStyle::of(size: Size::fixed(1)));
    }

    private static function blank(Ui $ui): Renderable
    {
        return self::row($ui, Line::plain(''));
    }

    private static function pipe(): Span
    {
        return Span::styled('  │  ', TextStyle::new()->fg(Color::indexed(238)));
    }

    private static function headerStyle(): TextStyle
    {
        return TextStyle::new()->fg(Color::indexed(252))->bold();
    }

    private static function dimStyle(): TextStyle
    {
        return TextStyle::new()->fg(Color::indexed(242));
    }

    private static function bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    /** @return list<Renderable> */
    private function leftRows(Ui $ui): array
    {
        $conversation = $this->store->conversation;
        $activity = $this->store->activity;
        $input = $this->store->input;
        $rows = [
            self::row($ui, Line::from(
                Span::styled('  ── DevTools ─────────────────────────────', self::headerStyle()),
            )),
            self::blank($ui),
            self::row($ui, Line::from(Span::styled('  Store Slices', self::headerStyle()))),
            self::kv(
                $ui,
                'repl.convo',
                count($conversation->exchangeIndexes()) . ' exchanges, scroll=' . $conversation->scrollOffset
                    . ', expanded=' . ($conversation->expandedIndex ?? 'none'),
            ),
            self::kv($ui, 'repl.status', 'Agent [' . strtolower($activity->status->name) . ']'),
            self::kv($ui, 'repl.input', mb_strlen($input->text) . ' chars'),
            self::kv($ui, 'repl.requests', count($this->store->requests->entries) . ' entries'),
            self::blank($ui),
        ];

        if ($conversation->messages !== []) {
            $rows[] = self::row($ui, Line::from(Span::styled('  Exchange History', self::headerStyle())));
            foreach ($conversation->exchangeIndexes() as $i => $messageIndex) {
                $user = $conversation->messages[$messageIndex];
                $rows[] = self::kv($ui, "  [{$i}]", 'user=' . mb_strlen($user->text) . 'c');
            }
            $rows[] = self::blank($ui);
        }

        $memReal = memory_get_usage(true);
        $memZend = memory_get_usage(false);
        $peakReal = memory_get_peak_usage(true);
        $peakZend = memory_get_peak_usage(false);

        $rows[] = self::row($ui, Line::from(Span::styled('  Memory (ZMM)', self::headerStyle())));
        $rows[] = self::kv($ui, 'heap used', self::bytes($memZend) . ' / ' . self::bytes($memReal));
        $rows[] = self::kv($ui, 'heap peak', self::bytes($peakZend) . ' / ' . self::bytes($peakReal));
        $rows[] = self::blank($ui);

        $gc = gc_status();
        $rows[] = self::row($ui, Line::from(Span::styled('  Garbage Collector', self::headerStyle())));
        $rows[] = self::kv($ui, 'runs', (string) $gc['runs']);
        $rows[] = self::kv($ui, 'collected', (string) $gc['collected']);
        $rows[] = self::kv($ui, 'root buffer', $gc['roots'] . ' / ' . $gc['buffer_size'] . ' slots');
        $rows[] = self::blank($ui);

        $rows[] = self::row($ui, Line::from(Span::styled('  Runtime', self::headerStyle())));
        $rows[] = self::kv($ui, 'php', PHP_VERSION . ' (' . PHP_SAPI . ')');
        $rows[] = self::kv($ui, 'os', PHP_OS . ' ' . php_uname('m'));
        $rows[] = self::kv($ui, 'pid', (string) getmypid());
        $rows[] = self::kv($ui, 'components', (string) count($this->mountSystem->mounted()));

        return $rows;
    }

    /** @return list<Renderable> */
    private function requestRows(Ui $ui): array
    {
        $rows = [
            self::row($ui, Line::from(Span::styled(' LLM Requests', self::headerStyle()))),
            self::blank($ui),
        ];

        if ($this->store->requests->entries === []) {
            $rows[] = self::row($ui, Line::from(Span::styled('  (no requests yet)', self::dimStyle())));

            return $rows;
        }

        foreach ($this->store->requests->entries as $i => $entry) {
            $rows[] = self::requestRow($ui, $entry, $i === $this->store->requests->focusedIndex);
        }

        return $rows;
    }
}
