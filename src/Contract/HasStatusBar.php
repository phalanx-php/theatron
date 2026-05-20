<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Contract;

use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;

interface HasStatusBar
{
    public function statusBar(Ui $ui): Renderable;
}
