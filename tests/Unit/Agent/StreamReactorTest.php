<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Agent;

use DateTimeImmutable;
use Phalanx\Panoply\Cue\Activity\Cancelled as ActivityCancelled;
use Phalanx\Panoply\Cue\Activity\Completed as ActivityCompleted;
use Phalanx\Panoply\Cue\Activity\Failed as ActivityFailed;
use Phalanx\Panoply\Cue\Activity\Started as ActivityStarted;
use Phalanx\Panoply\Cue\Effect\Denied as EffectDenied;
use Phalanx\Panoply\Cue\Effect\Executed as EffectExecuted;
use Phalanx\Panoply\Cue\Effect\Requested as EffectRequested;
use Phalanx\Panoply\Cue\Output\Channel;
use Phalanx\Panoply\Cue\Output\TokenDelta;
use Phalanx\Panoply\Cue\Output\TokenStop;
use Phalanx\Panoply\Cue\StopReason;
use Phalanx\Panoply\Cue\Usage\Delta as UsageDelta;
use Phalanx\Panoply\Cue\Usage\FinalUsage;
use Phalanx\Panoply\Effect\Kind;
use Phalanx\Theatron\Agent\StreamReactor;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Template\Slice\LlmRequestEntry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StreamReactorTest extends TestCase
{
    #[Test]
    public function tokenDeltaUpdatesConversation(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new TokenDelta('cue_1', 1, 'act_zeus', null, null, $at, 'Hail from Olympus'),
        ], $store);

        $messages = $store->conversation->messages;
        self::assertCount(1, $messages);
        self::assertSame('Hail from Olympus', $messages[0]->text);
        self::assertSame('assistant', $messages[0]->role);
        self::assertSame('message', $messages[0]->channel);
    }

    #[Test]
    public function tokenStopFinalizesMessage(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new TokenDelta('cue_1', 1, 'act_apollo', null, null, $at, 'Prophecy from Delphi'),
            new TokenStop('cue_2', 2, 'act_apollo', null, null, $at, StopReason::EndOfTurn),
        ], $store);

        $messages = $store->conversation->messages;
        self::assertCount(1, $messages);
        self::assertTrue($messages[0]->complete);
        self::assertFalse($store->conversation->isStreaming);
    }

    #[Test]
    public function thinkingChannelRoutesToThinkingBuffer(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new TokenDelta(
                id: 'cue_1',
                sequence: 1,
                activityId: 'act_zeus',
                invocationId: null,
                agentId: null,
                at: $at,
                text: 'Pondering the fate of Sparta...',
                channel: Channel::Thinking,
            ),
        ], $store);

        self::assertNotSame('', $store->conversation->thinkingBuffer);
        self::assertStringContainsString('Sparta', $store->conversation->thinkingBuffer);

        $messages = $store->conversation->messages;
        self::assertCount(1, $messages);
        self::assertSame('thinking', $messages[0]->channel);
    }

    #[Test]
    public function effectRequestedSetsAwaitingApproval(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new EffectRequested(
                id: 'cue_1',
                sequence: 1,
                activityId: 'act_ares',
                invocationId: null,
                agentId: null,
                at: $at,
                effectId: 'eff_1',
                kind: Kind::FileWrite,
                summary: 'Write battle plan to /var/plans/thermopylae.txt',
                arguments: ['path' => '/var/plans/thermopylae.txt'],
                requiresApproval: true,
            ),
        ], $store);

        self::assertSame(ActivityStatus::AwaitingApproval, $store->activity->status);
        self::assertNotNull($store->activity->pendingEffect);
        self::assertSame('file.write', $store->activity->pendingEffect->kind);
    }

    #[Test]
    public function effectRequestedWithoutApprovalDoesNotMutateActivity(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new EffectRequested(
                id: 'cue_1',
                sequence: 1,
                activityId: 'act_hephaestus',
                invocationId: null,
                agentId: null,
                at: $at,
                effectId: 'eff_2',
                kind: Kind::WebFetch,
                summary: 'Fetch forge blueprints',
                arguments: [],
                requiresApproval: false,
            ),
        ], $store);

        self::assertSame(ActivityStatus::Idle, $store->activity->status);
        self::assertNull($store->activity->pendingEffect);
    }

    #[Test]
    public function effectExecutedResolvesEffect(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new EffectRequested(
                id: 'cue_1',
                sequence: 1,
                activityId: 'act_poseidon',
                invocationId: null,
                agentId: null,
                at: $at,
                effectId: 'eff_3',
                kind: Kind::ShellExec,
                summary: 'Summon the seas',
                arguments: [],
                requiresApproval: true,
            ),
            new EffectExecuted('cue_2', 2, 'act_poseidon', null, null, $at, 'eff_3', 42),
        ], $store);

        self::assertSame(ActivityStatus::Running, $store->activity->status);
        self::assertNull($store->activity->pendingEffect);
    }

    #[Test]
    public function effectDeniedResolvesEffect(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new EffectRequested(
                id: 'cue_1',
                sequence: 1,
                activityId: 'act_hades',
                invocationId: null,
                agentId: null,
                at: $at,
                effectId: 'eff_4',
                kind: Kind::MemoryWrite,
                summary: 'Record souls crossing Styx',
                arguments: [],
                requiresApproval: true,
            ),
            new EffectDenied('cue_2', 2, 'act_hades', null, null, $at, 'eff_4', ['unauthorized']),
        ], $store);

        self::assertSame(ActivityStatus::Running, $store->activity->status);
    }

    #[Test]
    public function activityStartedSetsRunning(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new ActivityStarted('cue_1', 1, 'act_leonidas', null, 'agent_sparta', $at),
        ], $store);

        self::assertSame(ActivityStatus::Running, $store->activity->status);
    }

    #[Test]
    public function activityCompletedSetsCompleted(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new ActivityStarted('cue_1', 1, 'act_pericles', null, null, $at),
            new ActivityCompleted('cue_2', 2, 'act_pericles', null, null, $at),
        ], $store);

        self::assertSame(ActivityStatus::Completed, $store->activity->status);
    }

    #[Test]
    public function activityFailedSetsFailed(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new ActivityStarted('cue_1', 1, 'act_achilles', null, null, $at),
            new ActivityFailed('cue_2', 2, 'act_achilles', null, null, $at, 'Struck by Paris'),
        ], $store);

        self::assertSame(ActivityStatus::Failed, $store->activity->status);
    }

    #[Test]
    public function activityCancelledSetsCancelled(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new ActivityStarted('cue_1', 1, 'act_odysseus', null, null, $at),
            new ActivityCancelled('cue_2', 2, 'act_odysseus', null, null, $at, 'Sirens called'),
        ], $store);

        self::assertSame(ActivityStatus::Cancelled, $store->activity->status);
    }

    #[Test]
    public function usageDeltaUpdatesTokenCounters(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new UsageDelta('cue_1', 1, 'act_plato', null, null, $at, 150, 300),
        ], $store);

        self::assertSame(150, $store->activity->inputTokens);
        self::assertSame(300, $store->activity->outputTokens);
        self::assertSame(450, $store->activity->totalTokens);
    }

    #[Test]
    public function finalUsageReplacesTokenCounters(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new UsageDelta('cue_1', 1, 'act_plato', null, null, $at, 25, 75),
            new FinalUsage('cue_2', 2, 'act_plato', null, null, $at, 150, 300),
        ], $store);

        self::assertSame(150, $store->activity->inputTokens);
        self::assertSame(300, $store->activity->outputTokens);
        self::assertSame(450, $store->activity->totalTokens);
    }

    #[Test]
    public function finalUsageRefreshesFocusedRequestTokenCount(): void
    {
        $store = new AppStore();
        $store->requests = $store->requests
            ->append(new LlmRequestEntry('req-1', 'POST', '/first'))
            ->append(new LlmRequestEntry('req-2', 'POST', '/second'))
            ->completeById('req-2', 200, 25.0, 0, '{}');
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new FinalUsage('cue_1', 1, 'act_plato', null, null, $at, 150, 300),
        ], $store);

        self::assertSame(450, $store->requests->focused()?->tokenCount);
        self::assertSame(null, $store->requests->entries[0]->tokenCount);
    }

    #[Test]
    public function fullConversationSequence(): void
    {
        $store = new AppStore();
        $at = new DateTimeImmutable();

        StreamReactor::consume([
            new ActivityStarted('cue_1', 1, 'act_themistocles', null, 'agent_athens', $at),
            new TokenDelta('cue_2', 2, 'act_themistocles', null, 'agent_athens', $at, 'The fleet at'),
            new TokenDelta('cue_3', 3, 'act_themistocles', null, 'agent_athens', $at, ' Salamis'),
            new TokenDelta('cue_4', 4, 'act_themistocles', null, 'agent_athens', $at, ' holds the line.'),
            new TokenStop('cue_5', 5, 'act_themistocles', null, 'agent_athens', $at, StopReason::EndOfTurn),
            new ActivityCompleted('cue_6', 6, 'act_themistocles', null, 'agent_athens', $at),
        ], $store);

        self::assertSame(ActivityStatus::Completed, $store->activity->status);
        self::assertFalse($store->conversation->isStreaming);

        $messages = $store->conversation->messages;
        self::assertCount(1, $messages);
        self::assertSame('The fleet at Salamis holds the line.', $messages[0]->text);
        self::assertTrue($messages[0]->complete);
        self::assertSame('message', $messages[0]->channel);
    }
}
