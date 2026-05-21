<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Kit;

use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Input\KeyEvent;
use Phalanx\Theatron\Reactive\Signal;

trait TextInputBehavior
{
    abstract protected function inputSignal(): ?Signal;

    protected function handleTextInput(KeyEvent $event): bool
    {
        $signal = $this->inputSignal();

        if ($signal === null) {
            return false;
        }

        if ($event->is(Key::Backspace)) {
            $signal->set(static fn(string $value): string => mb_substr($value, 0, -1));

            return true;
        }

        $char = $event->char();

        if ($char !== null) {
            $signal->set(static fn(string $value): string => $value . $char);

            return true;
        }

        return false;
    }
}
