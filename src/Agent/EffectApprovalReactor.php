<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Agent;

use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Overlay\EffectApprovalOverlay;
use Phalanx\Theatron\Template\Slice\ActivityStatus;

final class EffectApprovalReactor
{
    public static function check(AppStore $store, Navigator $navigator): void
    {
        $activity = $store->activity;

        if ($activity->status !== ActivityStatus::AwaitingApproval) {
            return;
        }

        if ($activity->pendingEffect === null) {
            return;
        }

        $navigator->overlay(
            EffectApprovalOverlay::class,
            effect: $activity->pendingEffect,
        );
    }
}
