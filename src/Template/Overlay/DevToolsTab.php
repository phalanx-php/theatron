<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Overlay;

enum DevToolsTab: int
{
    case Metrics = 0;
    case Signals = 1;
    case Tree = 2;
    case Store = 3;
}
