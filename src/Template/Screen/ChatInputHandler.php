<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Screen;

use Phalanx\Theatron\Contract\AcceptsInput;
use Phalanx\Theatron\Contract\Focusable;
use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Kit\TextInputBehavior;
use Phalanx\Theatron\Reactive\Signal;

/**
 * Handles typed text input and Enter-to-submit for the chat composer.
 * Returned by ChatScreen::focusables() as the 'input' focusable.
 *
 * Characters are accumulated in ChatScreen::$inputText via TextInputBehavior.
 * Enter submits the accumulated text through ChatScreen::submitInput().
 */
final class ChatInputHandler implements Focusable, AcceptsInput
{
    use TextInputBehavior;

    public function __construct(
        private ChatScreen $screen,
    ) {
    }

    public function handleInput(KeyEvent $event): bool
    {
        if ($event->is(Key::Enter) && $this->screen->inputText->value !== '') {
            $this->screen->submitInput();

            return true;
        }

        return $this->handleTextInput($event);
    }

    protected function inputSignal(): Signal
    {
        return $this->screen->inputText;
    }
}
