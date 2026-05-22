<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template\Screen;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\InputElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\RowElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Screen\ChatConversationHandler;
use Phalanx\Theatron\Template\Screen\ChatInputHandler;
use Phalanx\Theatron\Template\Screen\ChatScreen;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Template\Slice\ConversationSlice;
use Phalanx\Theatron\Text\Line;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChatScreenTest extends TestCase
{
    #[Test]
    public function rendersEmptyConversationWithReplShell(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);
        $result = $screen($this->makeContext($store));

        self::assertInstanceOf(ColumnElement::class, $result);
        self::assertCount(7, $result->children);

        $text = self::flatten($result);
        self::assertStringContainsString('Theatron', $text);
        self::assertStringContainsString('Powered by Phalanx PHP', $text);
        self::assertStringContainsString('Type a message to begin.', $text);
        self::assertStringContainsString('Λ idle', $text);
        self::assertStringContainsString('+> ', $text);
    }

    #[Test]
    public function rendersSummariesAndExpandedCurrentExchange(): void
    {
        $store = new AppStore();
        $store->conversation = new ConversationSlice()
            ->addUserMessage('adding a couple messages')
            ->appendToken('Strategic Guidance The Hellespont is optimal for a flanking maneuver.')
            ->finalizeMessage()
            ->addUserMessage('and more')
            ->appendToken('Second preview should show in history.')
            ->finalizeMessage()
            ->addUserMessage('and another')
            ->appendToken('Final answer stays expanded.');

        $screen = new ChatScreen($store);

        $text = self::flatten($screen($this->makeContext($store)));

        self::assertStringContainsString('> adding a couple messages', $text);
        self::assertStringContainsString('Strategic Guidance The Hellespont', $text);
        self::assertStringContainsString('> and more', $text);
        self::assertStringContainsString('Second preview should show', $text);
        self::assertStringContainsString('you: and another', $text);
        self::assertStringContainsString('assistant:', $text);
        self::assertStringContainsString('Final answer stays expanded.', $text);
    }

    #[Test]
    public function statusBarRendersPocControls(): void
    {
        $screen = new ChatScreen(new AppStore());

        $text = self::flatten($screen->statusBar(new Ui()));

        self::assertStringContainsString('^P up', $text);
        self::assertStringContainsString('^N down', $text);
        self::assertStringContainsString('^D devtools', $text);
        self::assertStringContainsString('^S settings', $text);
        self::assertStringContainsString('Enter send', $text);
        self::assertStringContainsString('^C quit', $text);
    }

    #[Test]
    public function focusablesExposeConversationAndInputHandlers(): void
    {
        $screen = new ChatScreen(new AppStore());

        $focusables = $screen->focusables();

        self::assertCount(2, $focusables);
        self::assertSame('conversation', $focusables[0][0]);
        self::assertInstanceOf(ChatConversationHandler::class, $focusables[0][1]);
        self::assertSame('input', $focusables[1][0]);
        self::assertInstanceOf(ChatInputHandler::class, $focusables[1][1]);
    }

    #[Test]
    public function scrollingMovesThroughConversationExchanges(): void
    {
        $store = new AppStore();
        $store->conversation = $this->conversationWithUserMessages(4);
        $screen = new ChatScreen($store);

        self::assertSame(0, $store->conversation->scrollOffset);

        self::assertTrue($screen->handleScroll(new KeyEvent(Key::Up)));
        self::assertSame(1, $store->conversation->scrollOffset);

        self::assertTrue($screen->handleScroll(new KeyEvent('k')));
        self::assertSame(2, $store->conversation->scrollOffset);

        self::assertTrue($screen->handleScroll(new KeyEvent(Key::Down)));
        self::assertSame(1, $store->conversation->scrollOffset);

        self::assertTrue($screen->handleScroll(new KeyEvent('j')));
        self::assertSame(0, $store->conversation->scrollOffset);
    }

    #[Test]
    public function submitInputAddsUserMessageAndStartsActivity(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);

        $screen->inputText->set('Rally the phalanx at the agora.');

        self::assertTrue($screen->submitInput());
        self::assertCount(1, $store->conversation->messages);
        self::assertSame('user', $store->conversation->messages[0]->role);
        self::assertSame('Rally the phalanx at the agora.', $store->conversation->messages[0]->text);
        self::assertSame(ActivityStatus::Running, $store->activity->status);
        self::assertSame('', $screen->inputText->get());
        self::assertSame('', $store->input->text);
    }

    #[Test]
    public function submitInputQueuesWhenActivityIsBusy(): void
    {
        $store = new AppStore();
        $store->activity = new ActivitySlice()->withStatus(ActivityStatus::Running);
        $screen = new ChatScreen($store);

        $screen->inputText->set('Queue this while thinking.');

        self::assertTrue($screen->submitInput());
        self::assertSame([], $store->conversation->messages);
        self::assertSame(['Queue this while thinking.'], $store->input->queue);
    }

    #[Test]
    public function inputHandlerEnterSubmitsText(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);
        $handler = new ChatInputHandler($screen);

        $screen->inputText->set('Form the phalanx.');

        self::assertTrue($handler->handleInput(new KeyEvent(Key::Enter)));
        self::assertCount(1, $store->conversation->messages);
        self::assertSame('Form the phalanx.', $store->conversation->messages[0]->text);
    }

    #[Test]
    public function inputHandlerEnterExpandsScrolledHistoryInsteadOfSubmitting(): void
    {
        $store = new AppStore();
        $store->conversation = $this->conversationWithUserMessages(3)->scrollUp();
        $screen = new ChatScreen($store);
        $handler = new ChatInputHandler($screen);

        $screen->inputText->set('This should not submit yet.');

        self::assertTrue($handler->handleInput(new KeyEvent(Key::Enter)));
        self::assertNotNull($store->conversation->expandedIndex);
        self::assertCount(3, $store->conversation->messages);
    }

    #[Test]
    public function inputHandlerDelegatesTextEditingAndSyncsInputSlice(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);
        $handler = new ChatInputHandler($screen);

        $handler->handleInput(new KeyEvent('Z'));
        $handler->handleInput(new KeyEvent('e'));
        $handler->handleInput(new KeyEvent('u'));
        $handler->handleInput(new KeyEvent('s'));

        self::assertSame('Zeus', $screen->inputText->get());
        self::assertSame('Zeus', $store->input->text);

        $handler->handleInput(new KeyEvent(Key::Backspace));

        self::assertSame('Zeu', $screen->inputText->get());
        self::assertSame('Zeu', $store->input->text);
    }

    #[Test]
    public function thinkingStatusLineShowsQueueCount(): void
    {
        $store = new AppStore();
        $store->activity = new ActivitySlice()->withStatus(ActivityStatus::Running);
        $screen = new ChatScreen($store);
        $screen->inputText->set('first');
        $screen->submitInput();
        $screen->inputText->set('second');
        $screen->submitInput();

        $text = self::flatten($screen($this->makeContext($store)));

        self::assertStringContainsString('Λ running', $text);
        self::assertStringContainsString('2 queued', $text);
    }

    private static function flatten(Renderable|string $renderable): string
    {
        if (is_string($renderable)) {
            return $renderable;
        }

        if ($renderable instanceof TextElement) {
            return self::lineToText($renderable->content);
        }

        if ($renderable instanceof InputElement) {
            return self::lineToText($renderable->prompt) . $renderable->value;
        }

        if ($renderable instanceof ColumnElement || $renderable instanceof RowElement) {
            return implode("\n", array_map(self::flatten(...), $renderable->children));
        }

        if ($renderable instanceof PanelElement) {
            return self::lineToText($renderable->title) . "\n" . self::flatten($renderable->child);
        }

        return '';
    }

    private static function lineToText(string|Line $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        return implode('', array_map(static fn($span): string => $span->content, $content->spans));
    }

    private function conversationWithUserMessages(int $count): ConversationSlice
    {
        $conversation = new ConversationSlice();

        for ($i = 1; $i <= $count; $i++) {
            $conversation = $conversation->addUserMessage("Message {$i}.");
        }

        return $conversation;
    }

    private function makeContext(AppStore $store): ScreenContext
    {
        $scope = $this->createStub(TaskScope::class);
        $navigator = $this->createStub(Navigator::class);
        $mountSystem = new MountSystem($scope);

        return new ScreenContext($scope, new Ui(), Theme::default(), $navigator, $mountSystem);
    }
}
