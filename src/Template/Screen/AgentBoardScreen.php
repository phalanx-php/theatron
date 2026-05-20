<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Screen;

use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Template\Slice\AgentRegistrySlice;
use Phalanx\Theatron\Template\Slice\AgentSummary;

class AgentBoardScreen implements Screen
{
    public function __construct(
        private(set) AppStore $store,
    ) {
    }

    public function __invoke(ScreenContext $ctx): Renderable
    {
        $agents = $this->store->agents;
        $activity = $this->store->activity;

        return $ctx->ui->column(
            self::renderAgentCards($ctx->ui, $agents),
            $ctx->ui->divider(),
            self::renderStatusBar($ctx->ui, $agents, $activity),
        );
    }

    private static function renderAgentCards(Ui $ui, AgentRegistrySlice $agents): Renderable
    {
        if ($agents->agents === []) {
            return $ui->text('[muted]No agents registered[/muted]');
        }

        $cards = array_map(
            static fn (AgentSummary $agent) => self::renderAgentCard($ui, $agent, $agents->activeAgentId),
            $agents->agents,
        );

        return $ui->row(...$cards);
    }

    private static function renderAgentCard(Ui $ui, AgentSummary $agent, ?string $activeAgentId): Renderable
    {
        $lines = [];

        $lines[] = $ui->text('[bold]' . $agent->name . '[/bold]');
        $lines[] = $ui->text($agent->id);

        if ($agent->capabilities !== []) {
            $lines[] = $ui->text('Capabilities:');

            foreach ($agent->capabilities as $capability) {
                $lines[] = $ui->text('  - ' . $capability);
            }
        }

        if ($agent->id === $activeAgentId) {
            $lines[] = $ui->text('[bold]* Active[/bold]');
        }

        return $ui->panel($agent->name, $ui->column(...$lines));
    }

    private static function renderStatusBar(Ui $ui, AgentRegistrySlice $agents, ActivitySlice $activity): Renderable
    {
        $statusLabel = match ($activity->status) {
            ActivityStatus::Idle => 'Idle',
            ActivityStatus::Running => 'Running',
            ActivityStatus::AwaitingApproval => 'Awaiting Approval',
            ActivityStatus::Completed => 'Completed',
            ActivityStatus::Failed => 'Failed',
            ActivityStatus::Cancelled => 'Cancelled',
        };

        return $ui->statusLine(
            $ui->text('Status: ' . $statusLabel),
            $ui->text('Agents: ' . count($agents->agents)),
        );
    }
}
