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
use Phalanx\Theatron\Template\Slice\ConversationSlice;

class ChatScreen implements Screen
{
    public function __construct(
        private(set) AppStore $store,
    ) {
    }

    public function __invoke(ScreenContext $ctx): Renderable
    {
        $conversation = $this->store->conversation;
        $activity = $this->store->activity;

        return $ctx->ui->column(
            self::renderMessages($ctx->ui, $conversation),
            self::renderStreamingIndicator($ctx->ui, $conversation),
            $ctx->ui->divider(),
            self::renderInput($ctx->ui),
            self::renderStatusBar($ctx->ui, $activity),
        );
    }

    private static function renderMessages(Ui $ui, ConversationSlice $conversation): Renderable
    {
        if ($conversation->messages === []) {
            return $ui->scrollable('');
        }

        $lines = [];

        foreach ($conversation->messages as $message) {
            $prefix = $message->role === 'user'
                ? '[bold]You:[/bold]'
                : '[muted]Assistant:[/muted]';

            $lines[] = $prefix . ' ' . $message->text;
        }

        return $ui->scrollable(implode("\n", $lines));
    }

    private static function renderStreamingIndicator(Ui $ui, ConversationSlice $conversation): Renderable
    {
        if ($conversation->isStreaming) {
            return $ui->spinner('Streaming...');
        }

        return $ui->text('');
    }

    private static function renderInput(Ui $ui): Renderable
    {
        return $ui->input(value: '', prompt: '> ', cursor: 0);
    }

    private static function renderStatusBar(Ui $ui, ActivitySlice $activity): Renderable
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
            $ui->text($statusLabel),
            $ui->text(
                sprintf(
                    'Tokens: %d in / %d out / %d total',
                    $activity->inputTokens,
                    $activity->outputTokens,
                    $activity->totalTokens,
                ),
            ),
        );
    }
}
