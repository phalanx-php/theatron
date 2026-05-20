<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Agent;

use Phalanx\Athena\Activity\Activity;
use Phalanx\Athena\Activity\Config;
use Phalanx\Panoply\Agent;
use Phalanx\Panoply\Conversation\Log;
use Phalanx\Scope\TaskScope;

final class AgentExecutor implements AgentExecutorContract
{
    public function __construct(
        private(set) Activity $activity,
        private(set) TaskScope $scope,
        private(set) Agent $agent,
        private(set) Config $config,
    ) {
    }

    public function send(string $message): iterable
    {
        $log = Log::from([
            new \Phalanx\Panoply\Conversation\Record\Message(
                id: 'msg_' . \Phalanx\Panoply\Id::generate(),
                sequence: null,
                at: new \DateTimeImmutable(),
                role: 'user',
                text: $message,
            ),
        ]);

        $result = ($this->activity)($this->scope, $this->agent, $this->config, $log);

        return $result->stream;
    }

    public function cancel(): void
    {
        $this->scope->throwIfCancelled();
    }
}
