<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Overlay;

use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Kit\Metrics;
use Phalanx\Theatron\Layout\Border;
use Phalanx\Theatron\Layout\Size;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Style\Style as TextStyle;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Text\Line;
use Phalanx\Theatron\Text\Span;

class DevToolsOverlay implements Component
{
    private(set) Signal $activeTab;

    public function __construct(
        private(set) AppStore $store,
    ) {
        $this->activeTab = new Signal(0);
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        $tab = $this->activeTab->value;

        return $ctx->ui->column(
            self::renderTabBar($ctx->ui, $tab),
            $ctx->ui->panel(
                '',
                self::renderTabContent($ctx->ui, $tab, $this->store),
                style: Style::of(size: Size::fill(), border: Border::Rounded, color: Color::indexed(208)),
            ),
        );
    }

    private static function renderTabBar(Ui $ui, int $activeTab): Renderable
    {
        $tabs = ['Metrics', 'Store', 'Info'];
        $spans = [Span::styled(' ', TextStyle::new())];

        foreach ($tabs as $i => $label) {
            $style = $i === $activeTab
                ? TextStyle::new()->fg(Color::black())->bg(Color::indexed(208))->bold()
                : TextStyle::new()->fg(Color::indexed(245));

            $spans[] = Span::styled(" {$label} ", $style);
        }

        $spans[] = Span::styled(
            '  1/2/3:tab F12:close',
            TextStyle::new()->fg(Color::indexed(242)),
        );

        return $ui->text(Line::from(...$spans), Style::of(size: Size::fill()));
    }

    private static function renderTabContent(Ui $ui, int $tab, AppStore $store): Renderable
    {
        return match ($tab) {
            DevToolsTab::Store->value => self::renderStoreTab($ui, $store),
            DevToolsTab::Info->value  => self::renderInfoTab($ui),
            default                   => self::renderMetricsTab($ui, $store),
        };
    }

    private static function renderMetricsTab(Ui $ui, AppStore $store): Renderable
    {
        $conv     = $store->conversation;
        $agents   = $store->agents;
        $activity = $store->activity;

        $memReal = memory_get_usage(real_usage: true);
        $memPeak = memory_get_peak_usage(real_usage: true);

        $rows = [];

        $rows[] = $ui->row(
            $ui->text(
                self::label('msgs', (string) count($conv->messages)),
                Style::of(size: Size::fixed(14)),
            ),
            $ui->text(
                self::label('streaming', $conv->isStreaming ? 'yes' : 'no'),
                Style::of(size: Size::fixed(16)),
            ),
            $ui->text(
                self::label('thinking', (string) mb_strlen($conv->thinkingBuffer)),
                Style::of(size: Size::fixed(16)),
            ),
        );

        $rows[] = $ui->row(
            $ui->text(
                self::label('agents', (string) count($agents->agents)),
                Style::of(size: Size::fixed(12)),
            ),
            $ui->text(
                self::label('active', $agents->activeAgentId ?? 'none'),
                Style::of(size: Size::fixed(22)),
            ),
        );

        $rows[] = $ui->row(
            $ui->text(
                self::label('status', self::statusLabel($activity->status)),
                Style::of(size: Size::fixed(20)),
            ),
            $ui->text(
                self::label('in', (string) $activity->inputTokens),
                Style::of(size: Size::fixed(12)),
            ),
            $ui->text(
                self::label('out', (string) $activity->outputTokens),
                Style::of(size: Size::fixed(12)),
            ),
            $ui->text(
                self::label('total', (string) $activity->totalTokens),
                Style::of(size: Size::fixed(14)),
            ),
        );

        $rows[] = $ui->row(
            $ui->text(
                self::label('mem', Metrics::memory($memReal)),
                Style::of(size: Size::fixed(14)),
            ),
            $ui->text(
                self::label('peak', Metrics::memory($memPeak)),
                Style::of(size: Size::fixed(14)),
            ),
        );

        return $ui->column(...$rows);
    }

    private static function renderStoreTab(Ui $ui, AppStore $store): Renderable
    {
        $conv     = $store->conversation;
        $agents   = $store->agents;
        $activity = $store->activity;

        $dimStyle    = TextStyle::new()->fg(Color::indexed(245));
        $brightStyle = TextStyle::new()->fg(Color::brightWhite());
        $headStyle   = TextStyle::new()->fg(Color::indexed(245))->bold();

        $rows = [];

        $rows[] = $ui->text(Line::from(
            Span::styled(' ConversationSlice', $headStyle),
        ));

        $rows[] = $ui->text(Line::from(
            Span::styled('  messages:', $dimStyle),
            Span::styled((string) count($conv->messages), $brightStyle),
            Span::styled('  streaming:', $dimStyle),
            Span::styled($conv->isStreaming ? 'yes' : 'no', $brightStyle),
            Span::styled('  thinking:', $dimStyle),
            Span::styled((string) mb_strlen($conv->thinkingBuffer), $brightStyle),
        ));

        $rows[] = $ui->text(Line::from(
            Span::styled(' AgentRegistrySlice', $headStyle),
        ));

        $rows[] = $ui->text(Line::from(
            Span::styled('  agents:', $dimStyle),
            Span::styled((string) count($agents->agents), $brightStyle),
            Span::styled('  active:', $dimStyle),
            Span::styled($agents->activeAgentId ?? 'none', $brightStyle),
        ));

        $rows[] = $ui->text(Line::from(
            Span::styled(' ActivitySlice', $headStyle),
        ));

        $rows[] = $ui->text(Line::from(
            Span::styled('  status:', $dimStyle),
            Span::styled(self::statusLabel($activity->status), $brightStyle),
            Span::styled('  in:', $dimStyle),
            Span::styled((string) $activity->inputTokens, $brightStyle),
            Span::styled('  out:', $dimStyle),
            Span::styled((string) $activity->outputTokens, $brightStyle),
            Span::styled('  total:', $dimStyle),
            Span::styled((string) $activity->totalTokens, $brightStyle),
        ));

        return $ui->column(...$rows);
    }

    private static function renderInfoTab(Ui $ui): Renderable
    {
        $dimStyle    = TextStyle::new()->fg(Color::indexed(245));
        $brightStyle = TextStyle::new()->fg(Color::brightWhite());

        return $ui->column(
            $ui->text(Line::from(
                Span::styled(' phalanx-theatron', TextStyle::new()->fg(Color::indexed(208))->bold()),
            )),
            $ui->text(Line::from(
                Span::styled(' Component tree and signal inspection ', $dimStyle),
                Span::styled('require DevTools runtime.', $brightStyle),
            )),
            $ui->text(Line::from(
                Span::styled(' Available tabs:', $dimStyle),
                Span::styled(' Metrics ', $brightStyle),
                Span::styled('(runtime data)', $dimStyle),
                Span::styled('  Store ', $brightStyle),
                Span::styled('(slice snapshot)', $dimStyle),
            )),
        );
    }

    private static function label(string $name, string $value): Line
    {
        return Line::from(
            Span::styled(" {$name}:", TextStyle::new()->fg(Color::indexed(245))),
            Span::styled($value, TextStyle::new()->fg(Color::brightWhite())),
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
