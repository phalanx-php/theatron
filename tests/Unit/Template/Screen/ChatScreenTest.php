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
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\SpinnerElement;
use Phalanx\Theatron\Tdom\Element\StatusLineElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Screen\ChatConversationHandler;
use Phalanx\Theatron\Template\Screen\ChatInputHandler;
use Phalanx\Theatron\Template\Screen\ChatScreen;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\ConversationSlice;
use Phalanx\Theatron\Text\Line;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChatScreenTest extends TestCase
{
    #[Test]
    public function renderEmptyConversation(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);

        // Root is a three-child column: conversation panel, streaming indicator, input panel.
        self::assertInstanceOf(ColumnElement::class, $result);
        self::assertCount(3, $result->children);

        // [0] bordered conversation panel
        self::assertInstanceOf(PanelElement::class, $result->children[0]);

        // Empty state child is a TextElement
        $panel = $result->children[0];
        self::assertInstanceOf(PanelElement::class, $panel);
        self::assertSame('Conversation', $panel->title);
        self::assertInstanceOf(TextElement::class, $panel->child);

        // Empty state text is a Line (rich styled content)
        $textEl = $panel->child;
        self::assertInstanceOf(TextElement::class, $textEl);
        self::assertInstanceOf(Line::class, $textEl->content);

        // [2] input panel
        self::assertInstanceOf(PanelElement::class, $result->children[2]);
        $inputPanel = $result->children[2];
        self::assertSame('Input', $inputPanel->title);
    }

    #[Test]
    public function renderWithMessages(): void
    {
        $store = new AppStore();
        $store->conversation = new ConversationSlice()
            ->addUserMessage('Advance the phalanx, Leonidas commands it.')
            ->appendToken('By Zeus, we hold the pass at Thermopylae.');

        $screen = new ChatScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);

        self::assertInstanceOf(ColumnElement::class, $result);

        $panel = $result->children[0];
        self::assertInstanceOf(PanelElement::class, $panel);

        // With messages the child is a ColumnElement of TextElement rows.
        $body = $panel->child;
        self::assertInstanceOf(ColumnElement::class, $body);
        self::assertCount(2, $body->children);

        // Each row is a TextElement containing a Line (Span::styled rich content).
        foreach ($body->children as $row) {
            self::assertInstanceOf(TextElement::class, $row);
            self::assertInstanceOf(Line::class, $row->content);
        }

        // Spot-check span content includes role labels and message text.
        $userRow = $body->children[0];
        self::assertInstanceOf(TextElement::class, $userRow);
        $userLine = $userRow->content;
        self::assertInstanceOf(Line::class, $userLine);
        $combined = implode('', array_map(static fn($s) => $s->content, $userLine->spans));
        self::assertStringContainsString('You:', $combined);
        self::assertStringContainsString('Advance the phalanx', $combined);

        $assistantRow = $body->children[1];
        self::assertInstanceOf(TextElement::class, $assistantRow);
        $assistantLine = $assistantRow->content;
        self::assertInstanceOf(Line::class, $assistantLine);
        $combined2 = implode('', array_map(static fn($s) => $s->content, $assistantLine->spans));
        self::assertStringContainsString('Assistant:', $combined2);
        self::assertStringContainsString('Thermopylae', $combined2);
    }

    #[Test]
    public function statusBarRenders(): void
    {
        $store = new AppStore();
        $store->activity = new ActivitySlice()->updateUsage(300, 900);
        $screen = new ChatScreen($store);
        $ui = new Ui();

        $result = $screen->statusBar($ui);

        self::assertInstanceOf(StatusLineElement::class, $result);
        // StatusBar::render() produces sections from left/right/section calls.
        self::assertNotEmpty($result->sections);
    }

    #[Test]
    public function focusablesReturnsTwoEntries(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);

        $focusables = $screen->focusables();

        self::assertCount(2, $focusables);
        self::assertSame('conversation', $focusables[0][0]);
        self::assertInstanceOf(ChatConversationHandler::class, $focusables[0][1]);
        self::assertSame('input', $focusables[1][0]);
        self::assertInstanceOf(ChatInputHandler::class, $focusables[1][1]);
    }

    #[Test]
    public function scrollDown(): void
    {
        $store = new AppStore();

        // Add 15 messages so maxScroll > 0.
        $conv = new ConversationSlice();
        for ($i = 1; $i <= 15; $i++) {
            $conv = $conv->addUserMessage("Hoplite {$i} holds the line.");
        }
        $store->conversation = $conv;

        $screen = new ChatScreen($store);

        // Reflect to read private scrollOffset.
        $offsetProp = new \ReflectionProperty(ChatScreen::class, 'scrollOffset');

        self::assertSame(0, $offsetProp->getValue($screen));

        $screen->handleScroll(new KeyEvent('j'));
        self::assertSame(1, $offsetProp->getValue($screen));

        $screen->handleScroll(new KeyEvent(Key::Down));
        self::assertSame(2, $offsetProp->getValue($screen));
    }

    #[Test]
    public function scrollUp(): void
    {
        $store = new AppStore();
        $conv = new ConversationSlice();
        for ($i = 1; $i <= 15; $i++) {
            $conv = $conv->addUserMessage("Hoplite {$i} holds the line.");
        }
        $store->conversation = $conv;

        $screen = new ChatScreen($store);
        $offsetProp = new \ReflectionProperty(ChatScreen::class, 'scrollOffset');

        // Move down first, then back up.
        $screen->handleScroll(new KeyEvent('j'));
        $screen->handleScroll(new KeyEvent('j'));
        self::assertSame(2, $offsetProp->getValue($screen));

        $screen->handleScroll(new KeyEvent('k'));
        self::assertSame(1, $offsetProp->getValue($screen));

        $screen->handleScroll(new KeyEvent(Key::Up));
        self::assertSame(0, $offsetProp->getValue($screen));
    }

    #[Test]
    public function scrollGoToEnd(): void
    {
        $store = new AppStore();
        $conv = new ConversationSlice();
        for ($i = 1; $i <= 20; $i++) {
            $conv = $conv->addUserMessage("Leonidas message {$i}.");
        }
        $store->conversation = $conv;

        $screen = new ChatScreen($store);
        $offsetProp = new \ReflectionProperty(ChatScreen::class, 'scrollOffset');

        $screen->handleScroll(new KeyEvent('G'));
        // maxScroll = max(0, 20 - 12) = 8
        self::assertSame(8, $offsetProp->getValue($screen));
    }

    #[Test]
    public function submitInput(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);

        $screen->inputText->set('Rally the phalanx at the agora.');
        $screen->submitInput();

        $messages = $store->conversation->messages;
        self::assertCount(1, $messages);
        self::assertSame('user', $messages[0]->role);
        self::assertSame('Rally the phalanx at the agora.', $messages[0]->text);

        // Composer must be cleared after submit.
        self::assertSame('', $screen->inputText->get());
    }

    #[Test]
    public function submitInputIgnoresEmptyText(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);

        $screen->inputText->set('');
        $screen->submitInput();

        self::assertSame([], $store->conversation->messages);
    }

    #[Test]
    public function inputHandlerDelegatesToTextBehavior(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);
        $handler = new ChatInputHandler($screen);

        $handler->handleInput(new KeyEvent('Z'));
        $handler->handleInput(new KeyEvent('e'));
        $handler->handleInput(new KeyEvent('u'));
        $handler->handleInput(new KeyEvent('s'));

        self::assertSame('Zeus', $screen->inputText->get());

        // Backspace removes last char.
        $handler->handleInput(new KeyEvent(Key::Backspace));
        self::assertSame('Zeu', $screen->inputText->get());
    }

    #[Test]
    public function streamingIndicatorShownWhenRunning(): void
    {
        $store = new AppStore();
        $store->conversation = new ConversationSlice()
            ->addUserMessage('What does the oracle say?')
            ->appendToken('The oracle speaks...');

        self::assertTrue($store->conversation->isStreaming);
        // appendToken sets isStreaming but ActivitySlice status is still Idle by default.
        // The screen checks activity->status === Running for the spinner.
        $store->activity = new ActivitySlice()->effectResolved(); // sets Running

        $screen = new ChatScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);
        self::assertInstanceOf(ColumnElement::class, $result);

        $indicator = $result->children[1];
        self::assertInstanceOf(SpinnerElement::class, $indicator);
        self::assertSame('Streaming...', $indicator->label);
    }

    #[Test]
    public function streamingIndicatorHiddenWhenIdle(): void
    {
        $store = new AppStore();

        $screen = new ChatScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);
        self::assertInstanceOf(ColumnElement::class, $result);

        $indicator = $result->children[1];
        self::assertInstanceOf(TextElement::class, $indicator);
    }

    #[Test]
    public function scrollUpClampsAtZero(): void
    {
        $store = new AppStore();
        $conv = new ConversationSlice();
        for ($i = 1; $i <= 15; $i++) {
            $conv = $conv->addUserMessage("Spartan {$i} stands ready.");
        }
        $store->conversation = $conv;

        $screen = new ChatScreen($store);
        $offsetProp = new \ReflectionProperty(ChatScreen::class, 'scrollOffset');

        self::assertSame(0, $offsetProp->getValue($screen));

        // Press 'k' (up) at offset 0 — should stay at 0.
        $screen->handleScroll(new KeyEvent('k'));
        self::assertSame(0, $offsetProp->getValue($screen));
    }

    #[Test]
    public function scrollDownClampsAtMax(): void
    {
        $store = new AppStore();
        $conv = new ConversationSlice();
        for ($i = 1; $i <= 15; $i++) {
            $conv = $conv->addUserMessage("Pericles decree {$i}.");
        }
        $store->conversation = $conv;

        $screen = new ChatScreen($store);
        $offsetProp = new \ReflectionProperty(ChatScreen::class, 'scrollOffset');

        // maxScroll = max(0, 15 - 12) = 3. Jump to end.
        $screen->handleScroll(new KeyEvent('G'));
        self::assertSame(3, $offsetProp->getValue($screen));

        // Press 'j' (down) at max — should stay at 3.
        $screen->handleScroll(new KeyEvent('j'));
        self::assertSame(3, $offsetProp->getValue($screen));
    }

    #[Test]
    public function inputHandlerEnterSubmitsText(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);
        $handler = new ChatInputHandler($screen);

        $screen->inputText->set('Form the phalanx.');
        $result = $handler->handleInput(new KeyEvent(Key::Enter));

        self::assertTrue($result);
        self::assertCount(1, $store->conversation->messages);
        self::assertSame('user', $store->conversation->messages[0]->role);
        self::assertSame('Form the phalanx.', $store->conversation->messages[0]->text);
        self::assertSame('', $screen->inputText->get());
    }

    #[Test]
    public function inputHandlerEnterOnEmptyDoesNotSubmit(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);
        $handler = new ChatInputHandler($screen);

        // inputText is '' by default.
        $result = $handler->handleInput(new KeyEvent(Key::Enter));

        self::assertFalse($result);
        self::assertSame([], $store->conversation->messages);
    }

    private function makeContext(AppStore $store): ScreenContext
    {
        $scope = $this->createStub(TaskScope::class);
        $navigator = $this->createStub(Navigator::class);
        $mountSystem = new MountSystem($scope);

        return new ScreenContext($scope, new Ui(), Theme::default(), $navigator, $mountSystem);
    }
}
