<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Slice;

class LlmRequestSlice
{
    private const int MAX_ENTRIES = 50;

    /**
     * @param list<LlmRequestEntry> $entries
     */
    public function __construct(
        private(set) array $entries = [],
        private(set) int $focusedIndex = 0,
        private(set) int $detailScrollOffset = 0,
    ) {
    }

    public function append(LlmRequestEntry $entry): self
    {
        $entries = [...$this->entries, $entry];

        if (count($entries) > self::MAX_ENTRIES) {
            $entries = array_slice($entries, -self::MAX_ENTRIES);
        }

        return new self($entries, max(0, count($entries) - 1), $this->detailScrollOffset);
    }

    public function completeById(string $requestId, int $status, float $elapsedMs, int $tokenCount, string $body): self
    {
        $entries = $this->entries;
        $index = $this->findById($requestId);

        if ($index === null) {
            return $this;
        }

        $entries[$index] = $entries[$index]->markComplete($status, $elapsedMs, $tokenCount, $body);

        return new self(array_values($entries), $this->focusedIndex, $this->detailScrollOffset);
    }

    public function errorById(string $requestId, string $error, float $elapsedMs): self
    {
        $entries = $this->entries;
        $index = $this->findById($requestId);

        if ($index === null) {
            return $this;
        }

        $entries[$index] = $entries[$index]->markError($error, $elapsedMs);

        return new self(array_values($entries), $this->focusedIndex, $this->detailScrollOffset);
    }

    public function focusUp(): self
    {
        return new self($this->entries, max(0, $this->focusedIndex - 1), $this->detailScrollOffset);
    }

    public function focusDown(): self
    {
        $max = max(0, count($this->entries) - 1);

        return new self($this->entries, min($max, $this->focusedIndex + 1), $this->detailScrollOffset);
    }

    public function focused(): ?LlmRequestEntry
    {
        return $this->entries[$this->focusedIndex] ?? null;
    }

    public function scrollDetailUp(int $lines = 3): self
    {
        return new self($this->entries, $this->focusedIndex, max(0, $this->detailScrollOffset - $lines));
    }

    public function scrollDetailDown(int $lines = 3): self
    {
        return new self($this->entries, $this->focusedIndex, $this->detailScrollOffset + $lines);
    }

    public function resetDetailScroll(): self
    {
        return new self($this->entries, $this->focusedIndex, 0);
    }

    private function findById(string $requestId): ?int
    {
        foreach ($this->entries as $i => $entry) {
            if ($entry->requestId === $requestId) {
                return $i;
            }
        }

        return null;
    }
}
