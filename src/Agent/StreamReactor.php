<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Agent;

use Phalanx\Panoply\Cue;
use Phalanx\Panoply\Cue\Activity\Cancelled as ActivityCancelled;
use Phalanx\Panoply\Cue\Activity\Completed as ActivityCompleted;
use Phalanx\Panoply\Cue\Activity\Failed as ActivityFailed;
use Phalanx\Panoply\Cue\Activity\Started as ActivityStarted;
use Phalanx\Panoply\Cue\Effect\Denied as EffectDenied;
use Phalanx\Panoply\Cue\Effect\Executed as EffectExecuted;
use Phalanx\Panoply\Cue\Effect\Failed as EffectFailed;
use Phalanx\Panoply\Cue\Effect\Requested as EffectRequested;
use Phalanx\Panoply\Cue\Output\Channel;
use Phalanx\Panoply\Cue\Output\TokenDelta;
use Phalanx\Panoply\Cue\Output\TokenStop;
use Phalanx\Panoply\Cue\Usage\Delta as UsageDelta;
use Phalanx\Panoply\Cue\Usage\FinalUsage;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Template\Slice\PendingEffect;

final class StreamReactor
{
    /**
     * @param iterable<Cue> $cues
     */
    public static function consume(iterable $cues, AppStore $store): void
    {
        foreach ($cues as $cue) {
            self::dispatch($cue, $store);
        }
    }

    private static function dispatch(Cue $cue, AppStore $store): void
    {
        match (true) {
            $cue instanceof TokenStop => self::onTokenStop($store),
            $cue instanceof TokenDelta => self::onTokenDelta($cue, $store),
            $cue instanceof EffectFailed => self::onEffectResolved($store),
            $cue instanceof EffectDenied => self::onEffectResolved($store),
            $cue instanceof EffectExecuted => self::onEffectResolved($store),
            $cue instanceof EffectRequested => self::onEffectRequested($cue, $store),
            $cue instanceof ActivityStarted => self::onActivityStarted($store),
            $cue instanceof ActivityFailed => self::onActivityEnded($store, ActivityStatus::Failed),
            $cue instanceof ActivityCompleted => self::onActivityEnded($store, ActivityStatus::Completed),
            $cue instanceof ActivityCancelled => self::onActivityEnded($store, ActivityStatus::Cancelled),
            $cue instanceof UsageDelta => self::onUsageDelta($cue, $store),
            $cue instanceof FinalUsage => self::onFinalUsage($cue, $store),
            default => null,
        };
    }

    private static function onTokenDelta(TokenDelta $cue, AppStore $store): void
    {
        $channel = match ($cue->channel) {
            Channel::Message => 'message',
            Channel::Thinking, Channel::Reasoning => 'thinking',
        };

        $store->conversation = $store->conversation->appendToken($cue->text, $channel);
    }

    private static function onTokenStop(AppStore $store): void
    {
        $store->conversation = $store->conversation->finalizeMessage();
    }

    private static function onEffectRequested(EffectRequested $cue, AppStore $store): void
    {
        if (!$cue->requiresApproval) {
            return;
        }

        $effect = new PendingEffect(
            kind: $cue->kind->value,
            summary: $cue->summary,
            arguments: $cue->arguments,
            hazardLevel: 0,
        );

        $store->activity = $store->activity->awaitingApproval($effect);
    }

    private static function onEffectResolved(AppStore $store): void
    {
        $store->activity = $store->activity->effectResolved();
    }

    private static function onActivityStarted(AppStore $store): void
    {
        $store->activity = new ActivitySlice(status: ActivityStatus::Running);
    }

    private static function onActivityEnded(AppStore $store, ActivityStatus $terminal): void
    {
        $store->activity = $store->activity->activityEnded($terminal);
    }

    private static function onUsageDelta(UsageDelta $cue, AppStore $store): void
    {
        $store->activity = $store->activity->updateUsage($cue->inputTokens, $cue->outputTokens);
    }

    private static function onFinalUsage(FinalUsage $cue, AppStore $store): void
    {
        $store->activity = $store->activity->withUsage($cue->inputTokens, $cue->outputTokens);
        $store->requests = $store->requests->updateFocusedTokenCount($store->activity->totalTokens);
    }
}
