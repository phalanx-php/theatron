<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Slice;

class PendingEffect
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private(set) string $kind,
        private(set) string $summary,
        private(set) array $arguments,
        private(set) int $hazardLevel,
    ) {
    }
}
