<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Reactive;

use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Reactive\Watch;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WatchTest extends TestCase
{
    #[Test]
    public function firesEffectOnDepChange(): void
    {
        $sig = new Signal(1);
        $fired = 0;

        $watch = new Watch(
            static fn(): int => $sig->value,
            static function () use (&$fired): void {
                $fired++;
            },
        );

        $sig->value = 2;
        self::assertSame(1, $fired);

        unset($watch);
    }

    #[Test]
    public function effectReceivesNewAndOldValues(): void
    {
        $sig = new Signal(10);
        $capturedNew = null;
        $capturedOld = null;

        $watch = new Watch(
            static fn(): int => $sig->value,
            static function (mixed $new, mixed $old) use (&$capturedNew, &$capturedOld): void {
                $capturedNew = $new;
                $capturedOld = $old;
            },
        );

        $sig->value = 20;

        self::assertSame(20, $capturedNew);
        self::assertSame(10, $capturedOld);

        unset($watch);
    }

    #[Test]
    public function effectNotFiredWhenValueUnchanged(): void
    {
        $sig = new Signal(5);
        $fired = 0;

        new Watch(
            static fn(): int => $sig->value,
            static function () use (&$fired): void {
                $fired++;
            },
        );

        $sig->value = 5;
        self::assertSame(0, $fired);
    }

    #[Test]
    public function disposeStopsEffects(): void
    {
        $sig = new Signal(1);
        $fired = 0;

        $watch = new Watch(
            static fn(): int => $sig->value,
            static function () use (&$fired): void {
                $fired++;
            },
        );

        $watch->dispose();
        $sig->value = 2;

        self::assertSame(0, $fired);
    }

    #[Test]
    public function nonStaticSelectorThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Watch selector must be a static closure.');

        new Watch(
            fn(): int => 1,
            static function (): void {
            },
        );
    }

    #[Test]
    public function nonStaticEffectThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Watch effect must be a static closure.');

        new Watch(
            static fn(): int => 1,
            function (): void {
            },
        );
    }
}
