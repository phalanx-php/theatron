<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Slice;

use DateTimeImmutable;

class ConversationSlice
{
    /**
     * @param list<ConversationMessage> $messages
     */
    public function __construct(
        private(set) array $messages = [],
        private(set) bool $isStreaming = false,
        private(set) string $thinkingBuffer = '',
    ) {
    }

    public function addUserMessage(string $text): self
    {
        $message = new ConversationMessage(
            role: 'user',
            text: $text,
            channel: null,
            complete: true,
            at: new DateTimeImmutable(),
        );

        return new self(
            messages: [...$this->messages, $message],
            isStreaming: $this->isStreaming,
            thinkingBuffer: $this->thinkingBuffer,
        );
    }

    /**
     * @param 'message'|'thinking' $channel
     */
    public function appendToken(string $text, string $channel = 'message'): self
    {
        $messages = $this->messages;
        $last = $messages === [] ? null : $messages[count($messages) - 1];

        if ($last !== null && !$last->complete && $last->channel === $channel) {
            $updated = new ConversationMessage(
                role: 'assistant',
                text: $last->text . $text,
                channel: $channel,
                complete: false,
                at: $last->at,
            );
            $messages[count($messages) - 1] = $updated;
        } else {
            $messages[] = new ConversationMessage(
                role: 'assistant',
                text: $text,
                channel: $channel,
                complete: false,
                at: new DateTimeImmutable(),
            );
        }

        $thinkingBuffer = $channel === 'thinking'
            ? $this->thinkingBuffer . $text
            : $this->thinkingBuffer;

        return new self(
            messages: $messages,
            isStreaming: true,
            thinkingBuffer: $thinkingBuffer,
        );
    }

    public function finalizeMessage(): self
    {
        $messages = $this->messages;

        if ($messages !== []) {
            $last = $messages[count($messages) - 1];
            $messages[count($messages) - 1] = new ConversationMessage(
                role: $last->role,
                text: $last->text,
                channel: $last->channel,
                complete: true,
                at: $last->at,
            );
        }

        return new self(
            messages: $messages,
            isStreaming: false,
            thinkingBuffer: '',
        );
    }
}
