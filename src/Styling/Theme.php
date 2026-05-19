<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Styling;

class Theme
{
    private function __construct()
    {
    }

    public static function default(): self
    {
        return new self();
    }
}
