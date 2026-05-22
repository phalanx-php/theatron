<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Agent;

use Closure;
use Phalanx\Scope\ExecutionScope;
use Phalanx\Supervisor\TaskHandle;
use Phalanx\Theatron\Agent\AgentRuntime;
use Phalanx\Theatron\Agent\MockAgentExecutor;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Template\Slice\PendingEffect;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AgentRuntimeTest extends TestCase
{
    #[Test]
    public function denyCancelsCurrentAgentTaskAndClearsPendingEffect(): void
    {
        $cancelled = false;
        $scope = $this->createStub(ExecutionScope::class);
        $scope->method('go')->willReturnCallback(
            static function (Closure $task, ?string $name) use (&$cancelled): TaskHandle {
                if ($name === 'theatron-agent-send') {
                    return new TaskHandle(
                        id: 'send',
                        name: 'send',
                        cancel: static function () use (&$cancelled): void {
                            $cancelled = true;
                        },
                        snapshot: static fn(): null => null,
                    );
                }

                $task();

                return new TaskHandle(
                    id: 'runtime',
                    name: $name ?? 'runtime',
                    cancel: static function (): void {
                    },
                    snapshot: static fn(): null => null,
                );
            },
        );

        $store = new AppStore();
        $runtime = new AgentRuntime($store, new MockAgentExecutor());

        $runtime->send($scope, 'hold the line');
        $store->activity = new ActivitySlice(pendingEffect: new PendingEffect(
            kind: 'process.run',
            summary: 'Run a command',
            arguments: [],
            hazardLevel: 3,
            effectId: 'effect-1',
        ));

        $runtime->deny($scope);

        self::assertTrue($cancelled);
        self::assertNull($store->activity->pendingEffect);
        self::assertSame(ActivityStatus::Cancelled, $store->activity->status);
    }
}
