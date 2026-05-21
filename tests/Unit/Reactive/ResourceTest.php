<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Reactive;

use Closure;
use Phalanx\Cancellation\CancellationToken;
use Phalanx\Runtime\RuntimeContext;
use Phalanx\Scope\TaskScope;
use Phalanx\Supervisor\WaitReason;
use Phalanx\Task\Executable;
use Phalanx\Task\Scopeable;
use Phalanx\Theatron\Reactive\Resource;
use Phalanx\Trace\Trace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResourceTest extends TestCase
{
    #[Test]
    public function refreshSetsLoadingAndResolvesValue(): void
    {
        $dirty = 0;

        $resource = new Resource(
            fetcher: static fn(mixed $key): string => "result-{$key}",
            onDirty: static function () use (&$dirty): void {
                $dirty++;
            },
        );

        $resource->refresh('abc');

        self::assertTrue($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('result-abc', $resource->value);
        self::assertNull($resource->error);
        self::assertSame(2, $dirty);
    }

    #[Test]
    public function refreshCapturesErrorOnFailure(): void
    {
        $dirty = 0;

        $resource = new Resource(
            fetcher: static function (): never {
                throw new RuntimeException('fetch failed');
            },
            onDirty: static function () use (&$dirty): void {
                $dirty++;
            },
        );

        $resource->refresh();

        self::assertFalse($resource->ok);
        self::assertFalse($resource->loading);
        self::assertNull($resource->value);
        self::assertInstanceOf(RuntimeException::class, $resource->error);
        self::assertSame('fetch failed', $resource->error->getMessage());
    }

    #[Test]
    public function generationCounterPreventsStaleResults(): void
    {
        $callCount = 0;

        $resource = new Resource(
            fetcher: static function (mixed $key) use (&$callCount): string {
                $callCount++;
                return "v{$callCount}";
            },
        );

        $resource->refresh('a');
        self::assertSame('v1', $resource->value);

        $resource->refresh('b');
        self::assertSame('v2', $resource->value);
        self::assertTrue($resource->ok);
    }

    #[Test]
    public function repeatedRefreshesKeepTheSameResourceInstance(): void
    {
        $resource = new Resource(
            fetcher: static fn(mixed $key): string => "value-{$key}",
        );

        $observed = $resource;

        $resource->refresh('one');
        $resource->refresh('two');

        self::assertSame($observed, $resource);
        self::assertTrue($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('value-two', $resource->value);
    }

    #[Test]
    public function failedRefreshPreservesLastSuccessfulValue(): void
    {
        $calls = 0;

        $resource = new Resource(
            fetcher: static function () use (&$calls): string {
                $calls++;

                if ($calls === 2) {
                    throw new RuntimeException('later failure');
                }

                return 'last-good';
            },
        );

        $resource->refresh();
        $resource->refresh();

        self::assertTrue($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('last-good', $resource->value);
        self::assertInstanceOf(RuntimeException::class, $resource->error);
        self::assertSame('later failure', $resource->error->getMessage());
    }

    #[Test]
    public function asyncSupersessionIgnoresStaleResultAndDirtyNotification(): void
    {
        $dirty = 0;
        $scope = new QueuedResourceTaskScope();

        $resource = new Resource(
            fetcher: static fn(string $key): string => "result-{$key}",
            scope: $scope,
            onDirty: static function () use (&$dirty): void {
                $dirty++;
            },
        );

        $resource->refresh('slow');
        $resource->refresh('fast');

        self::assertSame(2, $scope->queuedCount());
        self::assertSame(2, $dirty);
        self::assertTrue($resource->loading);

        $scope->runQueued(1);

        self::assertTrue($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('result-fast', $resource->value);
        self::assertSame(3, $dirty);

        $scope->runQueued(0);

        self::assertSame('result-fast', $resource->value);
        self::assertSame(3, $dirty);
    }

    #[Test]
    public function disposeBeforeAsyncCompletionPreventsWritesAndDirtyNotification(): void
    {
        $dirty = 0;
        $scope = new QueuedResourceTaskScope();

        $resource = new Resource(
            fetcher: static fn(): string => 'late-result',
            scope: $scope,
            onDirty: static function () use (&$dirty): void {
                $dirty++;
            },
        );

        $resource->refresh();
        $resource->dispose();

        self::assertFalse($resource->loading);
        self::assertSame(1, $dirty);

        $scope->runQueued(0);

        self::assertFalse($resource->ok);
        self::assertFalse($resource->loading);
        self::assertNull($resource->value);
        self::assertNull($resource->error);
        self::assertSame(1, $dirty);
    }

    #[Test]
    public function streamAppendsChunksIntoBufferAndPromotesCompletedValue(): void
    {
        $dirty = 0;

        $resource = new Resource(
            fetcher: static fn(): iterable => ['Phalanx ', 'streaming ', 'works'],
            onDirty: static function () use (&$dirty): void {
                $dirty++;
            },
        );

        $resource->stream();

        self::assertTrue($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('Phalanx streaming works', $resource->buffer);
        self::assertSame('Phalanx streaming works', $resource->value);
        self::assertNull($resource->error);
        self::assertSame(5, $dirty);
    }

    #[Test]
    public function failedStreamPreservesLastCompletedValueAndExposesPartialBuffer(): void
    {
        $calls = 0;

        $resource = new Resource(
            fetcher: static function () use (&$calls): iterable {
                $calls++;

                if ($calls === 1) {
                    yield 'last ';
                    yield 'good';
                    return;
                }

                yield 'partial ';
                yield 'attempt';

                throw new RuntimeException('stream failed');
            },
        );

        $resource->stream();
        $resource->stream();

        self::assertTrue($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('last good', $resource->value);
        self::assertSame('partial attempt', $resource->buffer);
        self::assertInstanceOf(RuntimeException::class, $resource->error);
        self::assertSame('stream failed', $resource->error->getMessage());
    }

    #[Test]
    public function asyncStreamSupersessionIgnoresStaleChunksCompletionAndDirtyNotifications(): void
    {
        $dirty = 0;
        $scope = new QueuedResourceTaskScope();

        $resource = new Resource(
            fetcher: static fn(string $key): iterable => match ($key) {
                'slow' => ['stale ', 'stream'],
                'fast' => ['fresh ', 'stream'],
                default => throw new RuntimeException("Unexpected stream key {$key}."),
            },
            scope: $scope,
            onDirty: static function () use (&$dirty): void {
                $dirty++;
            },
        );

        $resource->stream('slow');
        $resource->stream('fast');

        self::assertSame(2, $scope->queuedCount());
        self::assertSame(2, $dirty);
        self::assertSame('', $resource->buffer);
        self::assertTrue($resource->loading);

        $scope->runQueued(1);

        self::assertTrue($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('fresh stream', $resource->buffer);
        self::assertSame('fresh stream', $resource->value);
        self::assertSame(5, $dirty);

        $scope->runQueued(0);

        self::assertSame('fresh stream', $resource->buffer);
        self::assertSame('fresh stream', $resource->value);
        self::assertSame(5, $dirty);
    }

    #[Test]
    public function disposeBeforeAsyncStreamCompletionPreventsWritesAndDirtyNotification(): void
    {
        $dirty = 0;
        $scope = new QueuedResourceTaskScope();

        $resource = new Resource(
            fetcher: static fn(): iterable => ['late ', 'stream'],
            scope: $scope,
            onDirty: static function () use (&$dirty): void {
                $dirty++;
            },
        );

        $resource->stream();
        $resource->dispose();

        self::assertFalse($resource->loading);
        self::assertSame('', $resource->buffer);
        self::assertSame(1, $dirty);

        $scope->runQueued(0);

        self::assertFalse($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('', $resource->buffer);
        self::assertNull($resource->value);
        self::assertNull($resource->error);
        self::assertSame(1, $dirty);
    }

    #[Test]
    public function streamCapturesNonIterableFetcherResultAsError(): void
    {
        $resource = new Resource(
            fetcher: static fn(): string => 'not iterable',
        );

        $resource->stream();

        self::assertFalse($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('', $resource->buffer);
        self::assertNull($resource->value);
        self::assertInstanceOf(RuntimeException::class, $resource->error);
        self::assertSame('Resource stream fetcher must return an iterable.', $resource->error->getMessage());
    }

    #[Test]
    public function streamCapturesNonStringChunkAsError(): void
    {
        $resource = new Resource(
            fetcher: static fn(): iterable => ['valid', 42],
        );

        $resource->stream();

        self::assertFalse($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('valid', $resource->buffer);
        self::assertNull($resource->value);
        self::assertInstanceOf(RuntimeException::class, $resource->error);
        self::assertSame('Resource stream chunks must be strings.', $resource->error->getMessage());
    }

    #[Test]
    public function refreshClearsPreviousStreamBuffer(): void
    {
        $calls = 0;

        $resource = new Resource(
            fetcher: static function () use (&$calls): mixed {
                $calls++;

                if ($calls === 1) {
                    return ['stream ', 'buffer'];
                }

                return 'fresh scalar';
            },
        );

        $resource->stream();
        $resource->refresh();

        self::assertTrue($resource->ok);
        self::assertFalse($resource->loading);
        self::assertSame('', $resource->buffer);
        self::assertSame('fresh scalar', $resource->value);
    }

    #[Test]
    public function disposalPreventsRefresh(): void
    {
        $fetched = false;

        $resource = new Resource(
            fetcher: static function () use (&$fetched): string {
                $fetched = true;
                return 'data';
            },
        );

        $resource->dispose();
        $resource->refresh();

        self::assertFalse($fetched);
        self::assertFalse($resource->loading);
        self::assertNull($resource->value);
    }

    #[Test]
    public function doubleDisposeIsSafe(): void
    {
        $resource = new Resource(
            fetcher: static fn(): string => 'data',
        );

        $resource->dispose();
        $resource->dispose();

        self::assertFalse($resource->loading);
    }

    #[Test]
    public function nonStaticFetcherThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Resource fetcher must be a static closure.');

        new Resource(fetcher: fn(): string => 'data');
    }

    #[Test]
    public function nonStaticOnDirtyThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Resource onDirty callback must be a static closure.');

        new Resource(
            fetcher: static fn(): string => 'data',
            onDirty: fn(): null => null,
        );
    }
}

final class QueuedResourceTaskScope implements TaskScope
{
    public bool $isCancelled {
        get => $this->token->isCancelled;
    }

    public RuntimeContext $runtime {
        get => throw new RuntimeException('Runtime is not implemented by the queued resource test scope.');
    }

    /** @var list<Scopeable|Executable|Closure> */
    private array $tasks = [];

    /** @var list<Closure(): void> */
    private array $disposeCallbacks = [];

    private CancellationToken $token;

    public function __construct()
    {
        $this->token = CancellationToken::create();
    }

    public function queuedCount(): int
    {
        return count($this->tasks);
    }

    public function runQueued(int $index): mixed
    {
        $task = $this->tasks[$index] ?? throw new RuntimeException("No queued task at index {$index}.");
        array_splice($this->tasks, $index, 1);

        if (!$task instanceof Closure) {
            throw new RuntimeException('Queued resource test scope only executes closures.');
        }

        return $task();
    }

    public function call(Closure $fn, ?WaitReason $waitReason = null): mixed
    {
        return $fn();
    }

    public function throwIfCancelled(): void
    {
        $this->token->throwIfCancelled();
    }

    public function cancellation(): CancellationToken
    {
        return $this->token;
    }

    public function onDispose(Closure $callback): void
    {
        $this->disposeCallbacks[] = $callback;
    }

    public function dispose(): void
    {
        $callbacks = array_reverse($this->disposeCallbacks);
        $this->disposeCallbacks = [];
        $this->token->cancel();

        foreach ($callbacks as $callback) {
            $callback();
        }
    }

    public function service(string $type): object
    {
        throw new RuntimeException("Service {$type} is not implemented by the queued resource test scope.");
    }

    public function trace(): Trace
    {
        throw new RuntimeException('Trace is not implemented by the queued resource test scope.');
    }

    public function execute(Scopeable|Executable|Closure $task): mixed
    {
        $this->tasks[] = $task;

        return null;
    }

    public function executeFresh(Scopeable|Executable|Closure $task): mixed
    {
        return $this->execute($task);
    }
}
