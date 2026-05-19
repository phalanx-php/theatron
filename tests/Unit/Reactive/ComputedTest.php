<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Reactive;

use Phalanx\Theatron\Reactive\Computed;
use Phalanx\Theatron\Reactive\Signal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ComputedTest extends TestCase
{
    #[Test]
    public function lazyEvaluation(): void
    {
        $evals = 0;
        $sig = new Signal(5);

        $computed = new Computed(static function () use ($sig, &$evals): int {
            $evals++;
            return $sig->value * 2;
        });

        self::assertSame(0, $evals);

        $result = $computed->value;
        self::assertSame(10, $result);
        self::assertSame(1, $evals);
    }

    #[Test]
    public function cachesResultUntilDepChanges(): void
    {
        $evals = 0;
        $sig = new Signal(3);

        $computed = new Computed(static function () use ($sig, &$evals): int {
            $evals++;
            return $sig->value + 1;
        });

        $_ = $computed->value;
        $_ = $computed->value;
        self::assertSame(1, $evals);

        $sig->value = 10;
        $_ = $computed->value;
        self::assertSame(2, $evals);
    }

    #[Test]
    public function autoRecomputesOnDepChange(): void
    {
        $sig = new Signal(2);
        $computed = new Computed(static fn(): int => $sig->value * 3);

        self::assertSame(6, $computed->value);

        $sig->value = 4;
        self::assertSame(12, $computed->value);
    }

    #[Test]
    public function circularDependencyThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular computed dependency detected.');

        $computed = null;
        $computed = new Computed(static function () use (&$computed): int {
            /** @var Computed $computed */
            return $computed->value + 1;
        });

        $_ = $computed->value;
    }

    #[Test]
    public function disposalCascadesDepSubscriptions(): void
    {
        $sig = new Signal(1);
        $evals = 0;

        $computed = new Computed(static function () use ($sig, &$evals): int {
            $evals++;
            return $sig->value;
        });

        $_ = $computed->value;
        self::assertSame(1, $evals);

        $computed->dispose();
        self::assertTrue($computed->isDisposed);

        $sig->value = 2;
        self::assertSame(1, $evals);
    }

    #[Test]
    public function nonStaticFactoryThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Computed factory must be a static closure.');

        new Computed(fn(): int => 1);
    }

    #[Test]
    public function subscriberNotifiedOnDirty(): void
    {
        $sig = new Signal(1);
        $notified = 0;

        $computed = new Computed(static fn(): int => $sig->value + 10);
        $_ = $computed->value;

        $computed->subscribe(static function () use (&$notified): void {
            $notified++;
        });

        $sig->value = 5;
        self::assertSame(1, $notified);
    }
}
