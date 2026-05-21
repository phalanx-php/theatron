<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Reactive;

final class RenderDependencySet
{
    public int $count {
        get => $this->countSubscriptions();
    }

    /** @var array<int, SignalSubscription|ComputedSubscription|ResourceSubscription> */
    private array $subscriptions = [];

    public function __construct(private DirtyBatch $batch)
    {
    }

    /** @param list<object> $deps */
    public function reconcile(array $deps): void
    {
        /** @var array<int, object> $next */
        $next = [];

        foreach ($deps as $dep) {
            if (
                $dep instanceof Signal
                || $dep instanceof Computed
                || $dep instanceof Resource
            ) {
                $next[spl_object_id($dep)] = $dep;
            }
        }

        foreach ($this->subscriptions as $id => $subscription) {
            if (isset($next[$id])) {
                continue;
            }

            $subscription->dispose();
            unset($this->subscriptions[$id]);
        }

        foreach ($next as $id => $dep) {
            if (isset($this->subscriptions[$id])) {
                continue;
            }

            $this->subscriptions[$id] = $this->subscribe($dep);
        }
    }

    public function dispose(): void
    {
        foreach ($this->subscriptions as $subscription) {
            $subscription->dispose();
        }

        $this->subscriptions = [];
    }

    private function subscribe(object $dep): SignalSubscription|ComputedSubscription|ResourceSubscription
    {
        $batch = $this->batch;
        $subscriber = static function () use ($batch): void {
            $batch->request();
        };

        if ($dep instanceof Signal) {
            return $dep->subscribe($subscriber);
        }

        if ($dep instanceof Computed) {
            return $dep->subscribe($subscriber);
        }

        /** @var Resource $dep */
        return $dep->subscribe($subscriber);
    }

    private function countSubscriptions(): int
    {
        return count($this->subscriptions);
    }
}
