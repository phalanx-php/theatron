#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Phalanx\Panoply\Cue\Activity\Completed as ActivityCompleted;
use Phalanx\Panoply\Cue\Activity\Started as ActivityStarted;
use Phalanx\Panoply\Cue\Output\Channel;
use Phalanx\Panoply\Cue\Output\TokenDelta;
use Phalanx\Panoply\Cue\Output\TokenStop;
use Phalanx\Panoply\Cue\StopReason;
use Phalanx\Panoply\Cue\Usage\Delta as UsageDelta;
use Phalanx\Theatron\Agent\MockAgentExecutor;
use Phalanx\Theatron\Agent\StreamReactor;
use Phalanx\Theatron\Template\AppStore;

// Scripted Panoply cue stream that mimics a Zeus oracle response.
// No OpenSwoole, no Ollama — validates the reactive pipeline from cues to store.

$activityId = 'zeus-oracle-001';
$now = new DateTimeImmutable();

$cues = [
    new ActivityStarted(
        id: 'cue-001',
        sequence: 1,
        activityId: $activityId,
        invocationId: null,
        agentId: 'zeus',
        at: $now,
    ),
    new TokenDelta(
        id: 'cue-002',
        sequence: 2,
        activityId: $activityId,
        invocationId: null,
        agentId: 'zeus',
        at: $now,
        text: 'From Olympus, ',
        channel: Channel::Message,
    ),
    new TokenDelta(
        id: 'cue-003',
        sequence: 3,
        activityId: $activityId,
        invocationId: null,
        agentId: 'zeus',
        at: $now,
        text: 'the phalanx holds.',
        channel: Channel::Message,
    ),
    new TokenStop(
        id: 'cue-004',
        sequence: 4,
        activityId: $activityId,
        invocationId: null,
        agentId: 'zeus',
        at: $now,
        reason: StopReason::EndOfTurn,
        channel: Channel::Message,
    ),
    new UsageDelta(
        id: 'cue-005',
        sequence: 5,
        activityId: $activityId,
        invocationId: null,
        agentId: 'zeus',
        at: $now,
        inputTokens: 12,
        outputTokens: 5,
    ),
    new ActivityCompleted(
        id: 'cue-006',
        sequence: 6,
        activityId: $activityId,
        invocationId: null,
        agentId: 'zeus',
        at: $now,
    ),
];

$executor = new MockAgentExecutor($cues);
$store = new AppStore();

StreamReactor::consume($executor->send('Speak, Zeus.'), $store);

$conversation = $store->conversation;
$activity = $store->activity;

echo "--- Theatron reactive pipeline demo ---\n";
echo sprintf("Messages: %d\n", count($conversation->messages));

foreach ($conversation->messages as $i => $message) {
    echo sprintf("  [%d] role=%s text=%s\n", $i, $message->role, $message->text);
}

echo sprintf("Streaming: %s\n", $conversation->isStreaming ? 'yes' : 'no');
echo sprintf("Activity status: %s\n", $activity->status->name);
echo sprintf("Input tokens: %d\n", $activity->inputTokens);
echo sprintf("Output tokens: %d\n", $activity->outputTokens);
echo sprintf("Total tokens: %d\n", $activity->totalTokens);
echo "--- done ---\n";
