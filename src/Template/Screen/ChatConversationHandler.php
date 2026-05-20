<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Screen;

use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Input\NormalModeHandler;

/**
 * Delegates normal-mode scroll keys (j / k / G / Up / Down) to ChatScreen.
 * Returned by ChatScreen::focusables() as the 'conversation' focusable.
 */
final class ChatConversationHandler implements NormalModeHandler
{
    public function __construct(
        private ChatScreen $screen,
    ) {
    }

    public function handleNormalKey(KeyEvent $event): bool
    {
        return $this->screen->handleScroll($event);
    }
}
