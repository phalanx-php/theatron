<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Agent;

final class MockAgentExecutor implements AgentExecutorContract
{
    private bool $cancelled = false;

    /**
     * @param list<\Phalanx\Panoply\Cue> $scriptedCues
     */
    public function __construct(
        private(set) array $scriptedCues = [],
    ) {
    }

    public function send(string $message): iterable
    {
        $this->reset();

        foreach ($this->scriptedCues as $cue) {
            yield $cue;

            if ($this->cancelled) {
                return;
            }
        }
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    /**
     * @param list<\Phalanx\Panoply\Cue> $cues
     */
    public function withCues(array $cues): self
    {
        return new self($cues);
    }

    private function reset(): void
    {
        $this->cancelled = false;
    }
}
