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
            $signal->value = mb_substr($signal->value, 0, -1);

            return true;
        }

        $char = $event->char();

        if ($char !== null) {
            $signal->value .= $char;

            return true;
        }

        return false;
    }
}
