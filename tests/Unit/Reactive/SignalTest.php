<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Reactive;

use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Reactive\Tracker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SignalTest extends TestCase
{
    #[Test]
    public function readRecordsAccessInTracker(): void
    {
        $signal = new Signal(42);

        $frame = Tracker::push();
        $read = $signal->value;
        $deps = Tracker::pop($frame);

        self::assertSame(42, $read);
        self::assertCount(1, $deps);
        self::assertSame($signal, $deps[0]);
    }

    #[Test]
    public function writeNotifiesSubscribers(): void
    {
        $signal = new Signal(1);
        $calls = 0;

        $signal->subscribe(static function () use (&$calls): void {
            $calls++;
        });

        $signal->value = 2;

        self::assertSame(1, $calls);
    }

    #[Test]
    public function equalitySkipSuppressesNotification(): void
    {
        $signal = new Signal('hello');
        $calls = 0;

        $signal->subscribe(static function () use (&$calls): void {
            $calls++;
        });

        $signal->value = 'hello';

        self::assertSame(0, $calls);
    }

    #[Test]
    public function disposalBlocksWrites(): void
    {
        $signal = new Signal(0);
        $signal->dispose();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot write to a disposed signal.');

        $signal->value = 1;
    }

    #[Test]
    public function disposalBlocksSubscribes(): void
    {
        $signal = new Signal(0);
        $signal->dispose();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot subscribe to a disposed signal.');

        $signal->subscribe(static function (): void {
        });
    }

    #[Test]
    public function disposedFlagIsSet(): void
    {
        $signal = new Signal(0);
        self::assertFalse($signal->isDisposed);

        $signal->dispose();
        self::assertTrue($signal->isDisposed);
    }

    #[Test]
    public function subscriberCountTracksSubscriptions(): void
    {
        $signal = new Signal(0);
        self::assertSame(0, $signal->subscriberCount);

        $sub = $signal->subscribe(static function (): void {
        });
        self::assertSame(1, $signal->subscriberCount);

        $sub->dispose();
        self::assertSame(0, $signal->subscriberCount);
    }

    #[Test]
    public function nonStaticSubscriberThrows(): void
    {
        $signal = new Signal(0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Signal subscribers must be static closures.');

        $signal->subscribe(function (): void {
        });
    }
}
