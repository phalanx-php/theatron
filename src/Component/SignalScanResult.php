<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Component;

use Phalanx\Theatron\Reactive\ResourceSubscription;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Reactive\SignalSubscription;
use Phalanx\Theatron\State\StoreSubscription;

final class SignalScanResult
{
    /**
     * @param list<Signal> $ownedSignals
     * @param list<SignalSubscription|ResourceSubscription> $subscriptions
     * @param list<StoreSubscription> $storeSubscriptions
     */
    public function __construct(
        private(set) array $ownedSignals,
        private(set) array $subscriptions,
        private(set) array $storeSubscriptions = [],
    ) {
    }
}
