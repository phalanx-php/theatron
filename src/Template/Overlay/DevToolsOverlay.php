<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Overlay;

use Phalanx\Theatron\Component\ComponentTreeNode;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Kit\Metrics;
use Phalanx\Theatron\Layout\Border;
use Phalanx\Theatron\Layout\Size;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Reactive\SignalRegistry;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Style\Style as TextStyle;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Text\Line;
use Phalanx\Theatron\Text\Span;

final class DevToolsOverlay implements Component
{
    private(set) Signal $activeTab;

    public function __construct(
        private(set) AppStore $store,
        private(set) MountSystem $mountSystem,
        private(set) SignalRegistry $registry,
    ) {
        $this->activeTab = new Signal(0);
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        $tab = $this->activeTab->get();

        return $ctx->ui->column(
            self::renderTabBar($ctx->ui, $ctx->theme, $tab),
            $ctx->ui->panel(
                '',
                self::renderTabContent($ctx->ui, $ctx->theme, $tab, $this->store, $this->mountSystem, $this->registry),
                style: Style::of(size: Size::fill(), border: Border::Rounded, color: Color::indexed(208)),
            ),
        );
    }

    private static function renderTabBar(Ui $ui, Theme $theme, int $activeTab): Renderable
    {
        $tabs = ['Metrics', 'Signals', 'Tree', 'Store'];
        $spans = [Span::styled(' ', TextStyle::new())];

        foreach ($tabs as $i => $label) {
            $style = $i === $activeTab
                ? TextStyle::new()->fg(Color::black())->bg(Color::indexed(208))->bold()
                : $theme->subtle;

            $spans[] = Span::styled(" {$label} ", $style);
        }

        $spans[] = Span::styled(
            '  1/2/3/4:tab F12:close',
            $theme->muted,
        );

        return $ui->text(Line::from(...$spans), Style::of(size: Size::fill()));
    }

    private static function renderTabContent(
        Ui $ui,
        Theme $theme,
        int $tab,
        AppStore $store,
        MountSystem $mountSystem,
        SignalRegistry $registry,
    ): Renderable {
        return match ($tab) {
            DevToolsTab::Signals->value => self::renderSignalsTab($ui, $theme, $registry),
            DevToolsTab::Tree->value    => self::renderTreeTab($ui, $theme, $mountSystem),
            DevToolsTab::Store->value   => self::renderStoreTab($ui, $theme, $store),
            default                     => self::renderMetricsTab($ui, $theme, $store, $mountSystem, $registry),
        };
    }

    private static function renderMetricsTab(
        Ui $ui,
        Theme $theme,
        AppStore $store,
        MountSystem $mountSystem,
        SignalRegistry $registry,
    ): Renderable {
        $conv     = $store->conversation;
        $agents   = $store->agents;
        $activity = $store->activity;

        $memReal     = memory_get_usage(real_usage: true);
        $memZend     = memory_get_usage(real_usage: false);
        $peakReal    = memory_get_peak_usage(real_usage: true);
        $peakZend    = memory_get_peak_usage(real_usage: false);

        $rows = [];

        $rows[] = $ui->row(
            $ui->text(
                self::label($theme, 'msgs', (string) count($conv->messages)),
                Style::of(size: Size::fixed(14)),
            ),
            $ui->text(
                self::label($theme, 'streaming', $conv->isStreaming ? 'yes' : 'no'),
                Style::of(size: Size::fixed(16)),
            ),
            $ui->text(
                self::label($theme, 'thinking', (string) mb_strlen($conv->thinkingBuffer)),
                Style::of(size: Size::fixed(16)),
            ),
        );

        $rows[] = $ui->row(
            $ui->text(
                self::label($theme, 'agents', (string) count($agents->agents)),
                Style::of(size: Size::fixed(12)),
            ),
            $ui->text(
                self::label($theme, 'active', $agents->activeAgentId ?? 'none'),
                Style::of(size: Size::fixed(22)),
            ),
        );

        $rows[] = $ui->row(
            $ui->text(
                self::label($theme, 'status', self::statusLabel($activity->status)),
                Style::of(size: Size::fixed(20)),
            ),
            $ui->text(
                self::label($theme, 'in', (string) $activity->inputTokens),
                Style::of(size: Size::fixed(12)),
            ),
            $ui->text(
                self::label($theme, 'out', (string) $activity->outputTokens),
                Style::of(size: Size::fixed(12)),
            ),
            $ui->text(
                self::label($theme, 'total', (string) $activity->totalTokens),
                Style::of(size: Size::fixed(14)),
            ),
        );

        $rows[] = $ui->row(
            $ui->text(
                self::label($theme, 'mem/r', Metrics::memory($memReal)),
                Style::of(size: Size::fixed(14)),
            ),
            $ui->text(
                self::label($theme, 'mem/z', Metrics::memory($memZend)),
                Style::of(size: Size::fixed(14)),
            ),
            $ui->text(
                self::label($theme, 'peak/r', Metrics::memory($peakReal)),
                Style::of(size: Size::fixed(16)),
            ),
            $ui->text(
                self::label($theme, 'peak/z', Metrics::memory($peakZend)),
                Style::of(size: Size::fixed(16)),
            ),
        );

        $rows[] = $ui->row(
            $ui->text(
                self::label($theme, 'comps', (string) count($mountSystem->mounted())),
                Style::of(size: Size::fixed(14)),
            ),
            $ui->text(
                self::label($theme, 'sigs', (string) $registry->count()),
                Style::of(size: Size::fixed(14)),
            ),
        );

        return $ui->column(...$rows);
    }

    private static function renderSignalsTab(Ui $ui, Theme $theme, SignalRegistry $registry): Renderable
    {
        $snapshots = $registry->snapshot();
        $headStyle = $theme->subtle->bold();
        $rows      = [];

        $rows[] = $ui->text(Line::from(
            Span::styled(' Signal', $headStyle),
            Span::styled(str_repeat(' ', 20), TextStyle::new()),
            Span::styled('Value', $headStyle),
            Span::styled(str_repeat(' ', 20), TextStyle::new()),
            Span::styled('Subs', $headStyle),
        ));

        foreach ($snapshots as $snapshot) {
            $nameStyle  = $snapshot->isDisposed ? $theme->muted : $theme->accent;
            $valueStyle = $snapshot->isDisposed ? $theme->muted : $theme->bright;

            $rows[] = $ui->text(Line::from(
                Span::styled(" {$snapshot->label}", $nameStyle),
                Span::styled(' = ', $theme->subtle),
                Span::styled($snapshot->value, $valueStyle),
                Span::styled(" ({$snapshot->subscriberCount})", $theme->muted),
            ));
        }

        if ($snapshots === []) {
            $rows[] = $ui->text(Line::from(
                Span::styled(' No signals registered', $theme->muted),
            ));
        }

        return $ui->column(...$rows);
    }

    private static function renderTreeTab(Ui $ui, Theme $theme, MountSystem $mountSystem): Renderable
    {
        $nodes = [];

        foreach ($mountSystem->mounted() as $mounted) {
            $nodes[] = new ComponentTreeNode(
                class: $mounted->component::class,
                signalCount: $mounted->signalCount,
                subscriptionCount: $mounted->subscriptionCount,
            );
        }

        $rows = [];

        foreach ($nodes as $node) {
            $pos = strrpos($node->class, '\\');
            $shortClass = $pos !== false ? substr($node->class, $pos + 1) : $node->class;

            $rows[] = $ui->text(Line::from(
                Span::styled(' ', TextStyle::new()),
                Span::styled($shortClass, $theme->accent->bold()),
                Span::styled(" sig:{$node->signalCount}", $theme->muted),
                Span::styled(" sub:{$node->subscriptionCount}", $theme->muted),
            ));
        }

        if ($nodes === []) {
            $rows[] = $ui->text(Line::from(
                Span::styled(' No components mounted', $theme->muted),
            ));
        }

        return $ui->column(...$rows);
    }

    private static function renderStoreTab(Ui $ui, Theme $theme, AppStore $store): Renderable
    {
        $conv     = $store->conversation;
        $agents   = $store->agents;
        $activity = $store->activity;

        $rows = [];

        $rows[] = $ui->text(Line::from(
            Span::styled(' ConversationSlice', $theme->subtle->bold()),
        ));

        $rows[] = $ui->text(Line::from(
            Span::styled('  messages:', $theme->subtle),
            Span::styled((string) count($conv->messages), $theme->bright),
            Span::styled('  streaming:', $theme->subtle),
            Span::styled($conv->isStreaming ? 'yes' : 'no', $theme->bright),
            Span::styled('  thinking:', $theme->subtle),
            Span::styled((string) mb_strlen($conv->thinkingBuffer), $theme->bright),
        ));

        $rows[] = $ui->text(Line::from(
            Span::styled(' AgentRegistrySlice', $theme->subtle->bold()),
        ));

        $rows[] = $ui->text(Line::from(
            Span::styled('  agents:', $theme->subtle),
            Span::styled((string) count($agents->agents), $theme->bright),
            Span::styled('  active:', $theme->subtle),
            Span::styled($agents->activeAgentId ?? 'none', $theme->bright),
        ));

        $rows[] = $ui->text(Line::from(
            Span::styled(' ActivitySlice', $theme->subtle->bold()),
        ));

        $rows[] = $ui->text(Line::from(
            Span::styled('  status:', $theme->subtle),
            Span::styled(self::statusLabel($activity->status), $theme->bright),
            Span::styled('  in:', $theme->subtle),
            Span::styled((string) $activity->inputTokens, $theme->bright),
            Span::styled('  out:', $theme->subtle),
            Span::styled((string) $activity->outputTokens, $theme->bright),
            Span::styled('  total:', $theme->subtle),
            Span::styled((string) $activity->totalTokens, $theme->bright),
        ));

        return $ui->column(...$rows);
    }

    private static function label(Theme $theme, string $name, string $value): Line
    {
        return Line::from(
            Span::styled(" {$name}:", $theme->subtle),
            Span::styled($value, $theme->bright),
        );
    }

    private static function statusLabel(ActivityStatus $status): string
    {
        return match ($status) {
            ActivityStatus::Idle             => 'Idle',
            ActivityStatus::Running          => 'Running',
            ActivityStatus::AwaitingApproval => 'Awaiting Approval',
            ActivityStatus::Completed        => 'Completed',
            ActivityStatus::Failed           => 'Failed',
            ActivityStatus::Cancelled        => 'Cancelled',
        };
    }
}
