<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Kit;

use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Kit\TextInputBehavior;
use Phalanx\Theatron\Reactive\Signal;

final class TextInputFixture
{
    use TextInputBehavior;

    public function __construct(private ?Signal $signal)
    {
    }

    public function signal(): ?Signal
    {
        return $this->signal;
    }

    public function handle(KeyEvent $event): bool
    {
        return $this->handleTextInput($event);
    }

    protected function inputSignal(): ?Signal
    {
        return $this->signal;
    }
}
