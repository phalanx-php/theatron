<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Component;

use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Reactive\SignalSubscription;

class SignalScanResult
{
    /**
     * @param list<Signal> $ownedSignals
     * @param list<SignalSubscription> $subscriptions
     */
    public function __construct(
        private(set) array $ownedSignals,
        private(set) array $subscriptions,
    ) {
    }
}
