<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Reactive;

use Phalanx\Theatron\Reactive\Resource;
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
