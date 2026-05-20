<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Slice;

use InvalidArgumentException;

class ActivitySlice
{
    public function __construct(
        private(set) ActivityStatus $status = ActivityStatus::Idle,
        private(set) ?PendingEffect $pendingEffect = null,
        private(set) int $inputTokens = 0,
        private(set) int $outputTokens = 0,
        private(set) int $totalTokens = 0,
    ) {
    }

    public function awaitingApproval(PendingEffect $effect): self
    {
        return new self(
            status: ActivityStatus::AwaitingApproval,
            pendingEffect: $effect,
            inputTokens: $this->inputTokens,
            outputTokens: $this->outputTokens,
            totalTokens: $this->totalTokens,
        );
    }

    public function effectResolved(): self
    {
        return new self(
            status: ActivityStatus::Running,
            pendingEffect: null,
            inputTokens: $this->inputTokens,
            outputTokens: $this->outputTokens,
            totalTokens: $this->totalTokens,
        );
    }

    public function activityEnded(ActivityStatus $terminal): self
    {
        if (
            $terminal !== ActivityStatus::Completed
            && $terminal !== ActivityStatus::Failed
            && $terminal !== ActivityStatus::Cancelled
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Status "%s" is not a terminal state; expected Completed, Failed, or Cancelled.',
                    $terminal->name,
                ),
            );
        }

        return new self(
            status: $terminal,
            pendingEffect: $this->pendingEffect,
            inputTokens: $this->inputTokens,
            outputTokens: $this->outputTokens,
            totalTokens: $this->totalTokens,
        );
    }

    public function updateUsage(int $inputTokens, int $outputTokens): self
    {
        $newInput = $this->inputTokens + $inputTokens;
        $newOutput = $this->outputTokens + $outputTokens;

        return new self(
            status: $this->status,
            pendingEffect: $this->pendingEffect,
            inputTokens: $newInput,
            outputTokens: $newOutput,
            totalTokens: $newInput + $newOutput,
        );
    }
}
