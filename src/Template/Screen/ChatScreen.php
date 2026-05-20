<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Screen;

use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Focusable;
use Phalanx\Theatron\Contract\HasFocusables;
use Phalanx\Theatron\Contract\HasStatusBar;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Input\InputMode;
use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Kit\StatusBar;
use Phalanx\Theatron\Layout\Border;
use Phalanx\Theatron\Layout\Size;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Style\Style;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style as TdomStyle;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Template\Slice\ConversationMessage;
use Phalanx\Theatron\Template\Slice\ConversationSlice;
use Phalanx\Theatron\Text\Line;
use Phalanx\Theatron\Text\Span;

/**
 * Main chat screen: bordered conversation panel, rich message rendering,
 * scroll support, input composer, streaming indicator, and status bar.
 *
 * Two focusable areas:
 *   'conversation' — NormalModeHandler for j/k/G scroll
 *   'input'        — AcceptsInput for typed text and Enter-to-submit
 */
class ChatScreen implements Screen, HasStatusBar, HasFocusables
{
    /** Visible message rows above the fold; adjust for your terminal height. */
    private const int VISIBLE_ROWS = 12;

    private(set) Signal $inputText;
    private(set) ChatConversationHandler $conversationHandler;
    private(set) ChatInputHandler $inputHandler;
    private int $scrollOffset = 0;

    public function __construct(
        private(set) AppStore $store,
    ) {
        $this->inputText = new Signal('');
        $this->conversationHandler = new ChatConversationHandler($this);
        $this->inputHandler = new ChatInputHandler($this);
    }

    public function __invoke(ScreenContext $ctx): Renderable
    {
        $conversation = $this->store->conversation;
        $activity = $this->store->activity;
        $inputMode = $this->store->inputMode;
        $focusTarget = $inputMode->focusTarget;

        return $ctx->ui->column(
            $this->renderConversation($ctx->ui, $conversation, $focusTarget === 'conversation'),
            $this->renderStreamingIndicator($ctx->ui, $activity),
            $this->renderInput($ctx->ui, $focusTarget === 'input'),
        );
    }

    public function statusBar(Ui $ui): Renderable
    {
        $activity = $this->store->activity;
        $inputMode = $this->store->inputMode;

        return StatusBar::new()
            ->section($this->modeLabel($inputMode->mode), $this->modeColor($inputMode->mode))
            ->left(sprintf('Chat  %s', $inputMode->focusTarget ?? ''))
            ->right(sprintf(
                'Tokens: %d in / %d out',
                $activity->inputTokens,
                $activity->outputTokens,
            ))
            ->render($ui);
    }

    /** @return list<array{string, Focusable}> */
    public function focusables(): array
    {
        return [
            ['conversation', $this->conversationHandler],
            ['input', $this->inputHandler],
        ];
    }

    /**
     * Called by ChatConversationHandler. Moves the scroll offset within message bounds.
     */
    public function handleScroll(KeyEvent $event): bool
    {
        $messages = $this->store->conversation->messages;
        $maxScroll = max(0, count($messages) - self::VISIBLE_ROWS);

        if ($event->is('j') || $event->is(Key::Down)) {
            $this->scrollOffset = min($maxScroll, $this->scrollOffset + 1);

            return true;
        }

        if ($event->is('k') || $event->is(Key::Up)) {
            $this->scrollOffset = max(0, $this->scrollOffset - 1);

            return true;
        }

        if ($event->is('G')) {
            $this->scrollOffset = $maxScroll;

            return true;
        }

        return false;
    }

    /**
     * Called by ChatInputHandler on Enter. Appends the current input text as a
     * user message and clears the composer.
     */
    public function submitInput(): void
    {
        $text = $this->inputText->value;

        if ($text === '') {
            return;
        }

        $this->store->mutate(
            ConversationSlice::class,
            static fn(ConversationSlice $s) => $s->addUserMessage($text),
        );

        $this->inputText->value = '';
    }

    // ---- private render methods ----

    private static function renderMessage(Ui $ui, ConversationMessage $msg): Renderable
    {
        $roleColor = match ($msg->role) {
            'user' => Style::new()->fg(Color::brightWhite())->bold(),
            'assistant' => Style::new()->fg(Color::brightCyan()),
            default => Style::new()->fg(Color::indexed(250)),
        };

        $roleLabel = $msg->role === 'user' ? 'You' : 'Assistant';

        $line = Line::from(
            Span::styled($roleLabel . ':', $roleColor),
            Span::styled(' ' . $msg->text, Style::new()->fg(Color::brightWhite())),
        );

        return $ui->text($line);
    }

    private static function renderStreamingIndicator(Ui $ui, ActivitySlice $activity): Renderable
    {
        if ($activity->status === ActivityStatus::Running) {
            return $ui->spinner('Streaming...');
        }

        return $ui->text('');
    }

    private static function modeLabel(InputMode $mode): string
    {
        return match ($mode) {
            InputMode::Normal => ' NORMAL ',
            InputMode::Insert => ' INSERT ',
        };
    }

    private static function modeColor(InputMode $mode): Color
    {
        return match ($mode) {
            InputMode::Normal => Color::brightCyan(),
            InputMode::Insert => Color::brightGreen(),
        };
    }

    private function renderConversation(Ui $ui, ConversationSlice $conversation, bool $focused): Renderable
    {
        $borderColor = $focused ? Color::brightYellow() : Color::indexed(240);
        $panelStyle = TdomStyle::of(size: Size::fill(), border: Border::Rounded, color: $borderColor);

        if ($conversation->messages === []) {
            $empty = $ui->text(
                Line::from(Span::styled('No messages yet.', Style::new()->fg(Color::indexed(242)))),
            );

            return $ui->panel('Conversation', $empty, style: $panelStyle);
        }

        $visible = array_slice($conversation->messages, $this->scrollOffset, self::VISIBLE_ROWS);
        $rows = array_map(
            static fn(ConversationMessage $msg) => self::renderMessage($ui, $msg),
            $visible,
        );

        return $ui->panel('Conversation', $ui->column(...$rows), style: $panelStyle);
    }

    private function renderInput(Ui $ui, bool $focused): Renderable
    {
        $borderColor = $focused ? Color::brightGreen() : Color::indexed(240);
        $panelStyle = TdomStyle::of(size: Size::fixed(3), border: Border::Rounded, color: $borderColor);
        $text = $this->inputText->value;

        return $ui->panel(
            'Input',
            $ui->input(value: $text, prompt: '> ', cursor: mb_strlen($text)),
            style: $panelStyle,
        );
    }
}
