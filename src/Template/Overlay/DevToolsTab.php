<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template\Overlay;

enum DevToolsTab: int
{
    case Metrics = 0;
    case Store = 1;
    case Info = 2;
}
