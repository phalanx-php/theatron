<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Agent;

use Phalanx\Panoply\Runtime;
use Phalanx\Panoply\Transport;
use Phalanx\Panoply\Transport\Request;
use Phalanx\Panoply\Transport\Sync\HttpError;
use Phalanx\Theatron\Agent\LlmRequestRecordingTransport;
use Phalanx\Theatron\Template\AppStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LlmRequestRecordingTransportTest extends TestCase
{
    #[Test]
    public function recordsRequestAndCompletionWhileYieldingChunks(): void
    {
        $store = new AppStore();
        $store->activity = $store->activity->withUsage(100, 212);

        $transport = new LlmRequestRecordingTransport(
            inner: new ScriptedTransport(['data: one', 'data: two']),
            store: $store,
            clock: self::clock(10.0, 10.0395),
            requestIds: static fn(): string => 'req-1',
        );

        $chunks = iterator_to_array($transport->stream(
            Request::of('POST', 'https://example.com/api/chat?stream=1', body: '{"model":"qwen3:4b"}'),
            new NullRuntime(),
        ), false);

        self::assertSame(['data: one', 'data: two'], $chunks);

        $entry = $store->requests->focused();
        self::assertNotNull($entry);
        self::assertSame('req-1', $entry->requestId);
        self::assertSame('POST', $entry->method);
        self::assertSame('/api/chat?stream=1', $entry->path);
        self::assertSame(200, $entry->status);
        self::assertSame(312, $entry->tokenCount);
        self::assertSame('{"model":"qwen3:4b"}', $entry->requestBody);
        self::assertSame('data: onedata: two', $entry->responseBody);
        self::assertTrue($entry->complete);
        self::assertNull($entry->error);
        self::assertEqualsWithDelta(39.5, $entry->elapsedMs ?? 0.0, 0.001);
    }

    #[Test]
    public function recordsHttpErrorStatusAndBodyBeforeRethrowing(): void
    {
        $store = new AppStore();
        $transport = new LlmRequestRecordingTransport(
            inner: new FailingTransport(new HttpError(429, '{"error":"rate limited"}', 'HTTP 429')),
            store: $store,
            clock: self::clock(1.0, 1.25),
            requestIds: static fn(): string => 'req-429',
        );

        try {
            iterator_to_array($transport->stream(
                Request::of('POST', 'https://example.com/v1/messages', body: '{}'),
                new NullRuntime(),
            ), false);

            self::fail('Expected HttpError to be rethrown.');
        } catch (HttpError $e) {
            self::assertSame(429, $e->statusCode);
        }

        $entry = $store->requests->focused();
        self::assertNotNull($entry);
        self::assertSame('req-429', $entry->requestId);
        self::assertSame('/v1/messages', $entry->path);
        self::assertSame(429, $entry->status);
        self::assertSame('{"error":"rate limited"}', $entry->responseBody);
        self::assertTrue($entry->complete);
        self::assertNull($entry->error);
        self::assertEqualsWithDelta(250.0, $entry->elapsedMs ?? 0.0, 0.001);
    }

    #[Test]
    public function recordsGenericTransportErrorBeforeRethrowing(): void
    {
        $store = new AppStore();
        $transport = new LlmRequestRecordingTransport(
            inner: new FailingTransport(new \RuntimeException('connection refused')),
            store: $store,
            clock: self::clock(2.0, 2.5),
            requestIds: static fn(): string => 'req-error',
        );

        try {
            iterator_to_array($transport->stream(
                Request::of('GET', 'not-a-url'),
                new NullRuntime(),
            ), false);

            self::fail('Expected RuntimeException to be rethrown.');
        } catch (\RuntimeException $e) {
            self::assertSame('connection refused', $e->getMessage());
        }

        $entry = $store->requests->focused();
        self::assertNotNull($entry);
        self::assertSame('GET', $entry->method);
        self::assertSame('not-a-url', $entry->path);
        self::assertTrue($entry->complete);
        self::assertSame('connection refused', $entry->error);
        self::assertEqualsWithDelta(500.0, $entry->elapsedMs ?? 0.0, 0.001);
    }

    #[Test]
    public function recordsMultipleRequestsWithUniqueIdsAndFocusesLatest(): void
    {
        $store = new AppStore();
        $ids = ['req-1', 'req-2'];

        $transport = new LlmRequestRecordingTransport(
            inner: new ScriptedTransport(['{}']),
            store: $store,
            clock: self::clock(1.0, 1.01, 2.0, 2.02),
            requestIds: static function () use (&$ids): string {
                $id = array_shift($ids);
                self::assertNotNull($id);

                return $id;
            },
        );

        iterator_to_array(
            $transport->stream(Request::of('POST', 'https://example.com/first'), new NullRuntime()),
            false,
        );
        iterator_to_array(
            $transport->stream(Request::of('POST', 'https://example.com/second'), new NullRuntime()),
            false,
        );

        self::assertCount(2, $store->requests->entries);
        self::assertSame('req-2', $store->requests->focused()?->requestId);
        self::assertSame('/first', $store->requests->entries[0]->path);
        self::assertSame('/second', $store->requests->entries[1]->path);
    }

    /**
     * @return \Closure(): float
     */
    private static function clock(float ...$times): \Closure
    {
        return static function () use (&$times): float {
            $time = array_shift($times);
            self::assertNotNull($time);

            return $time;
        };
    }
}

final class ScriptedTransport implements Transport
{
    /**
     * @param list<string> $chunks
     */
    public function __construct(
        private(set) array $chunks,
    ) {
    }

    public function stream(Request $request, Runtime $runtime): \Generator
    {
        foreach ($this->chunks as $chunk) {
            yield $chunk;
        }
    }
}

final class FailingTransport implements Transport
{
    public function __construct(
        private(set) \Throwable $failure,
    ) {
    }

    public function stream(Request $request, Runtime $runtime): \Generator
    {
        throw $this->failure;
    }
}

final class NullRuntime implements Runtime
{
    public function call(\Closure $work, ?string $waitReason = null): mixed
    {
        return $work();
    }

    public function isCancelled(): bool
    {
        return false;
    }

    public function throwIfCancelled(): void
    {
    }

    public function onCancel(\Closure $cleanup): void
    {
    }
}
