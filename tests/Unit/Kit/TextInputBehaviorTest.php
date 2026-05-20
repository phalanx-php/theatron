<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Kit;

use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Reactive\Signal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextInputBehaviorTest extends TestCase
{
    #[Test]
    public function appendsCharacterToSignal(): void
    {
        $fixture = new TextInputFixture(new Signal(''));
        $handled = $fixture->handle(new KeyEvent('a'));

        self::assertTrue($handled);
        self::assertNotNull($fixture->signal());
        self::assertSame('a', $fixture->signal()->value);
    }

    #[Test]
    public function backspaceTrimsLastCharacter(): void
    {
        $fixture = new TextInputFixture(new Signal('hello'));
        $handled = $fixture->handle(new KeyEvent(Key::Backspace));

        self::assertTrue($handled);
        self::assertNotNull($fixture->signal());
        self::assertSame('hell', $fixture->signal()->value);
    }

    #[Test]
    public function returnsFalseWhenNonPrintableKeyPressed(): void
    {
        $fixture = new TextInputFixture(new Signal(''));
        $handled = $fixture->handle(new KeyEvent(Key::Enter));

        self::assertFalse($handled);
    }

    #[Test]
    public function returnsFalseWhenSignalIsNull(): void
    {
        $fixture = new TextInputFixture(null);

        self::assertFalse($fixture->handle(new KeyEvent('a')));
    }

    #[Test]
    public function backspaceOnEmptyStringStaysEmpty(): void
    {
        $fixture = new TextInputFixture(new Signal(''));
        $handled = $fixture->handle(new KeyEvent(Key::Backspace));

        self::assertTrue($handled);
        self::assertNotNull($fixture->signal());
        self::assertSame('', $fixture->signal()->value);
    }

    #[Test]
    public function spaceKeyAppendsSpace(): void
    {
        $fixture = new TextInputFixture(new Signal('hello'));
        $handled = $fixture->handle(new KeyEvent(Key::Space));

        self::assertTrue($handled);
        self::assertNotNull($fixture->signal());
        self::assertSame('hello ', $fixture->signal()->value);
    }

    #[Test]
    public function backspaceRemovesLastMultiByteCharacter(): void
    {
        $fixture = new TextInputFixture(new Signal('αβγ'));
        $handled = $fixture->handle(new KeyEvent(Key::Backspace));

        self::assertTrue($handled);
        self::assertNotNull($fixture->signal());
        self::assertSame('αβ', $fixture->signal()->value);
    }
}
