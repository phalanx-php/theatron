<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Overlay;

use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Template\Slice\AgentRegistrySlice;
use Phalanx\Theatron\Template\Slice\ConversationSlice;

class DevToolsOverlay implements Component
{
    public function __construct(
        private(set) AppStore $store,
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->panel('DevTools', $ctx->ui->column(
            self::renderConversationPanel($ctx->ui, $this->store->conversation),
            self::renderAgentsPanel($ctx->ui, $this->store->agents),
            self::renderActivityPanel($ctx->ui, $this->store->activity),
        ));
    }

    private static function renderConversationPanel(Ui $ui, ConversationSlice $conversation): Renderable
    {
        $thinkingState = $conversation->thinkingBuffer !== ''
            ? mb_strimwidth($conversation->thinkingBuffer, 0, 40, '...')
            : '(empty)';

        return $ui->panel('Conversation', $ui->column(
            $ui->text(sprintf('Messages: %d', count($conversation->messages))),
            $ui->text(sprintf('Streaming: %s', $conversation->isStreaming ? 'true' : 'false')),
            $ui->text(sprintf('Thinking buffer: %s', $thinkingState)),
        ));
    }

    private static function renderAgentsPanel(Ui $ui, AgentRegistrySlice $agents): Renderable
    {
        $activeAgent = $agents->activeAgentId ?? 'none';

        return $ui->panel('Agents', $ui->column(
            $ui->text(sprintf('Registered: %d', count($agents->agents))),
            $ui->text(sprintf('Active: %s', $activeAgent)),
        ));
    }

    private static function renderActivityPanel(Ui $ui, ActivitySlice $activity): Renderable
    {
        $statusLabel = match ($activity->status) {
            ActivityStatus::Idle => 'Idle',
            ActivityStatus::Running => 'Running',
            ActivityStatus::AwaitingApproval => 'Awaiting Approval',
            ActivityStatus::Completed => 'Completed',
            ActivityStatus::Failed => 'Failed',
            ActivityStatus::Cancelled => 'Cancelled',
        };

        $effectInfo = $activity->pendingEffect !== null
            ? $activity->pendingEffect->kind . ': ' . $activity->pendingEffect->summary
            : 'none';

        return $ui->panel('Activity', $ui->column(
            $ui->text(sprintf('Status: %s', $statusLabel)),
            $ui->text(sprintf('Pending effect: %s', $effectInfo)),
            $ui->text(sprintf(
                'Tokens: %d in / %d out / %d total',
                $activity->inputTokens,
                $activity->outputTokens,
                $activity->totalTokens,
            )),
        ));
    }
}
