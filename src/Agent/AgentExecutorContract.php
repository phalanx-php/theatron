<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Agent;

interface AgentExecutorContract
{
    /**
     * Send a user message and get back a cue stream.
     *
     * @return iterable<\Phalanx\Panoply\Cue>
     */
    public function send(string $message): iterable;

    /**
     * Cancel the current activity if one is running.
     */
    public function cancel(): void;
}
