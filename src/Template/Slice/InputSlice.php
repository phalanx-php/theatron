<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Slice;

class InputSlice
{
    /** @param list<string> $queue */
    public function __construct(
        private(set) string $text = '',
        private(set) array $queue = [],
    ) {
    }

    public function withText(string $text): self
    {
        return new self($text, $this->queue);
    }

    public function clear(): self
    {
        return $this->withText('');
    }

    public function enqueue(string $message): self
    {
        return new self($this->text, [...$this->queue, $message]);
    }

    public function dequeue(): self
    {
        if ($this->queue === []) {
            return $this;
        }

        return new self($this->text, array_slice($this->queue, 1));
    }

    public function peek(): ?string
    {
        return $this->queue[0] ?? null;
    }
}
