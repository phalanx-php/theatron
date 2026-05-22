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
use Phalanx\Theatron\Layout\Size;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Style\Style as TextStyle;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style as TdomStyle;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Render\MarkdownRenderer;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Template\Slice\ConversationMessage;
use Phalanx\Theatron\Template\Slice\ConversationSlice;
use Phalanx\Theatron\Text\Line;
use Phalanx\Theatron\Text\Span;

class ChatScreen implements Screen, HasStatusBar, HasFocusables, DeclaresBindings
{
    private const array PULSE_COLORS = [242, 245, 248, 251, 254, 251, 248, 245];

    private(set) Signal $inputText;
    private(set) ChatConversationHandler $conversationHandler;
    private(set) ChatInputHandler $inputHandler;
    private MarkdownRenderer $markdown;

    public function __construct(
        private(set) AppStore $store,
    ) {
        $this->inputText = new Signal('');
        $this->conversationHandler = new ChatConversationHandler($this);
        $this->inputHandler = new ChatInputHandler($this);
        $this->markdown = new MarkdownRenderer();
    }

    public function __invoke(ScreenContext $ctx): Renderable
    {
        return $ctx->ui->column(
            $this->renderConversation($ctx->ui, 120, 18),
            self::spacer($ctx->ui),
            $this->renderStatusLine($ctx->ui),
            self::spacer($ctx->ui),
            $this->renderInput($ctx->ui),
            self::rule($ctx->ui, 30),
            self::spacer($ctx->ui),
        );
    }

    public function statusBar(Ui $ui): Renderable
    {
        return $ui->text(
            Line::from(
                Span::styled('  ^P', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' up', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('^N', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' down', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('^D', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' devtools', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('^S', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' settings', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('Enter', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' send', TextStyle::new()->fg(Color::indexed(250))),
                self::pipe(),
                Span::styled('^C', TextStyle::new()->fg(Color::indexed(245))),
                Span::styled(' quit', TextStyle::new()->fg(Color::indexed(250))),
            ),
            TdomStyle::of(size: Size::fixed(1)),
        );
    }

    /** @return list<array{string, Focusable}> */
    public function focusables(): array
    {
        return [
            ['conversation', $this->conversationHandler],
            ['input', $this->inputHandler],
        ];
    }

    /** @return list<Binding> */
    public function bindings(): array
    {
        return [
            Binding::ctrl('p')->label('up'),
            Binding::ctrl('n')->label('down'),
            Binding::key(Key::Enter)->label('send'),
        ];
    }

    public function handleScroll(KeyEvent $event): bool
    {
        if ($event->is('j') || $event->is(Key::Down) || ($event->ctrl && $event->is('n'))) {
            $this->store->conversation = $this->store->conversation->scrollDown();

            return true;
        }

        if ($event->is('k') || $event->is(Key::Up) || ($event->ctrl && $event->is('p'))) {
            $this->store->conversation = $this->store->conversation->scrollUp();

            return true;
        }

        if ($event->is('G')) {
            $slice = $this->store->conversation;
            foreach ($slice->exchangeIndexes() as $_) {
                $slice = $slice->scrollUp();
            }
            $this->store->conversation = $slice;

            return true;
        }

        return false;
    }

    public function submitOrExpand(): bool
    {
        $conversation = $this->store->conversation;

        if ($conversation->scrollOffset > 0) {
            $this->store->conversation = $conversation->expandAtScroll();

            return true;
        }

        return $this->submitInput();
    }

    public function submitInput(): bool
    {
        $text = trim((string) $this->inputText->get());

        if ($text === '') {
            return false;
        }

        $this->inputText->set('');
        $this->store->input = $this->store->input->clear();

        if ($this->store->activity->isBusy()) {
            $this->store->input = $this->store->input->enqueue($text);

            return true;
        }

        $this->store->conversation = $this->store->conversation->addUserMessage($text);
        $this->store->activity = $this->store->activity->withStatus(ActivityStatus::Running);

        return true;
    }

    public function syncInputText(): void
    {
        $this->store->input = $this->store->input->withText($this->inputText->get());
    }

    private static function row(Ui $ui, Line $line): Renderable
    {
        return $ui->text($line, TdomStyle::of(size: Size::fixed(1)));
    }

    private static function spacer(Ui $ui): Renderable
    {
        return self::row($ui, Line::plain(''));
    }

    private static function rule(Ui $ui, int $width): Renderable
    {
        return self::row($ui, Line::from(
            Span::styled('  ' . str_repeat('╴', $width), TextStyle::new()->fg(Color::indexed(236))),
        ));
    }

    private static function pipe(): Span
    {
        return Span::styled('  │  ', TextStyle::new()->fg(Color::indexed(238)));
    }

    /**
     * @return list<Line>
     */
    private static function wrapIndented(string $text, int $maxWidth, string $indent, TextStyle $style): array
    {
        $lineWidth = max(10, $maxWidth - mb_strlen($indent));
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($current === '') {
                $current = $word;
            } elseif (mb_strlen($current) + 1 + mb_strlen($word) <= $lineWidth) {
                $current .= ' ' . $word;
            } else {
                $lines[] = Line::from(Span::styled($indent . $current, $style));
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = Line::from(Span::styled($indent . $current, $style));
        }

        return $lines ?: [Line::from(Span::styled($indent, $style))];
    }

    /**
     * @param list<Renderable> $rows
     * @return list<Renderable>
     */
    private static function viewport(array $rows, int $maxRows, bool $stickToBottom, Ui $ui): array
    {
        if (count($rows) <= $maxRows) {
            if (!$stickToBottom) {
                return $rows;
            }

            $spacers = [];
            for ($i = 0; $i < $maxRows - count($rows); $i++) {
                $spacers[] = self::spacer($ui);
            }

            return [...$spacers, ...$rows];
        }

        return $stickToBottom
            ? array_slice($rows, -$maxRows)
            : array_slice($rows, 0, $maxRows);
    }

    private function renderConversation(Ui $ui, int $width, int $availableHeight): Renderable
    {
        $conversation = $this->store->conversation;
        $rows = [
            self::row($ui, Line::from(
                Span::styled("  Λ̬ ", TextStyle::new()->fg(Color::indexed(250))),
                Span::styled('Theatron', TextStyle::new()->fg(Color::indexed(250))->bold()),
                self::pipe(),
                Span::styled('Powered by Phalanx PHP', TextStyle::new()->fg(Color::indexed(242))),
            )),
        ];

        $body = $this->renderConversationRows($ui, $conversation, max(20, $width - 2));
        $visible = self::viewport($body, max(1, $availableHeight - 1), $conversation->scrollOffset === 0, $ui);

        return new ColumnElement([...$rows, ...$visible], TdomStyle::of(size: Size::fill()));
    }

    /** @return list<Renderable> */
    private function renderConversationRows(Ui $ui, ConversationSlice $conversation, int $wrapWidth): array
    {
        if ($conversation->messages === []) {
            return [
                self::row($ui, Line::from(
                    Span::styled('  Type a message to begin.', TextStyle::new()->fg(Color::indexed(242))),
                )),
            ];
        }

        if ($conversation->expandedIndex !== null) {
            return $this->renderExchange($ui, $conversation, $conversation->expandedIndex, $wrapWidth);
        }

        $rows = [];
        $indexes = $conversation->exchangeIndexes();
        $lastUserIndex = end($indexes);

        foreach ($indexes as $userIndex) {
            if ($userIndex === $lastUserIndex) {
                $rows = [...$rows, ...$this->renderExchange($ui, $conversation, $userIndex, $wrapWidth)];
                continue;
            }

            $rows = [...$rows, ...$this->renderSummary($ui, $conversation, $userIndex, $wrapWidth)];
        }

        return $rows;
    }

    /** @return list<Renderable> */
    private function renderSummary(Ui $ui, ConversationSlice $conversation, int $userIndex, int $wrapWidth): array
    {
        $user = $conversation->messages[$userIndex];
        $assistant = $this->assistantAfter($conversation, $userIndex);
        $rows = [];

        $userStyle = TextStyle::new()->fg(Color::indexed(250));
        foreach (self::wrapIndented($user->text, $wrapWidth, '  > ', $userStyle) as $line) {
            $rows[] = self::row($ui, $line);
        }

        $summaryRule = '  ' . str_repeat('─', min((int) ($wrapWidth * 0.6), 80));
        $rows[] = self::row($ui, Line::from(
            Span::styled($summaryRule, TextStyle::new()->fg(Color::indexed(238))),
        ));

        if ($assistant !== null) {
            $preview = MarkdownRenderer::stripSyntax(mb_substr($assistant->text, 0, 100));
            $previewStyle = TextStyle::new()->fg(Color::indexed(245));
            foreach (self::wrapIndented($preview, $wrapWidth, '    ', $previewStyle) as $line) {
                $rows[] = self::row($ui, $line);
            }
        }

        $rows[] = self::row($ui, Line::plain(''));

        return $rows;
    }

    /** @return list<Renderable> */
    private function renderExchange(Ui $ui, ConversationSlice $conversation, int $userIndex, int $wrapWidth): array
    {
        $user = $conversation->messages[$userIndex];
        $assistant = $this->assistantAfter($conversation, $userIndex);
        $rows = [self::row($ui, Line::plain(''))];
        $rows[] = self::row($ui, Line::from(
            Span::styled('  you: ', TextStyle::new()->fg(Color::indexed(255))->bold()),
            Span::styled($user->text, TextStyle::new()->fg(Color::indexed(252))),
        ));
        $exchangeRule = '  ' . str_repeat('─', min(24, (int) ($wrapWidth * 0.2)));
        $rows[] = self::row($ui, Line::from(Span::styled($exchangeRule, TextStyle::new()->fg(Color::indexed(236)))));

        if ($assistant !== null) {
            $rows[] = self::row($ui, Line::from(
                Span::styled('  assistant:', TextStyle::new()->fg(Color::indexed(252))->bold()),
            ));
            $rows = [...$rows, ...$this->markdown->render($ui, $assistant->text, $wrapWidth, '    ')];
        }

        if ($conversation->showThinking && $conversation->thinkingBuffer !== '') {
            $thinkingStyle = TextStyle::new()->fg(Color::indexed(242));
            foreach (self::wrapIndented($conversation->thinkingBuffer, $wrapWidth, '    ', $thinkingStyle) as $line) {
                $rows[] = self::row($ui, $line);
            }
        }

        return $rows;
    }

    private function renderStatusLine(Ui $ui): Renderable
    {
        $activity = $this->store->activity;
        $input = $this->store->input;
        $status = strtolower($activity->status->name);
        $color = $status === 'idle'
            ? Color::indexed(242)
            : Color::indexed(self::PULSE_COLORS[intdiv($activity->pulseFrame, 3) % count(self::PULSE_COLORS)]);
        $spans = [
            Span::styled('  Λ ', TextStyle::new()->fg($color)),
            Span::styled($status, TextStyle::new()->fg(Color::indexed(242))),
        ];

        if ($input->queue !== []) {
            $count = count($input->queue);
            $spans[] = self::pipe();
            $queuedText = $count === 1 ? '1 queued' : "{$count} queued";
            $spans[] = Span::styled($queuedText, TextStyle::new()->fg(Color::indexed(242)));
        }

        return self::row($ui, Line::from(...$spans));
    }

    private function renderInput(Ui $ui): Renderable
    {
        $text = (string) $this->inputText->get();

        return $ui->input(
            value: $text,
            prompt: '  +> ',
            cursor: mb_strlen($text),
            style: TdomStyle::of(size: Size::fixed(1)),
        );
    }

    private function assistantAfter(ConversationSlice $conversation, int $userIndex): ?ConversationMessage
    {
        for ($i = $userIndex + 1; $i < count($conversation->messages); $i++) {
            $message = $conversation->messages[$i];

            if ($message->role === 'user') {
                return null;
            }

            if ($message->channel !== 'thinking') {
                return $message;
            }
        }

        return null;
    }
}
