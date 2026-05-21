<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Ui;

use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\MountElement;
use Phalanx\Theatron\Tdom\Element\PanelElement;
use Phalanx\Theatron\Tdom\Element\RowElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Style;
use Phalanx\Theatron\Text\Line;

function text(string|Line $content, ?Style $style = null): TextElement
{
    return new TextElement($content, $style);
}

function panel(string $title, Renderable $child, ?Style $style = null): PanelElement
{
    return new PanelElement($title, $child, $style);
}

function column(Renderable ...$children): ColumnElement
{
    return new ColumnElement(array_values($children));
}

function row(Renderable ...$children): RowElement
{
    return new RowElement(array_values($children));
}

/**
 * @template T of Component
 * @param class-string<T> $component
 */
function mount(string $component, mixed ...$props): MountElement
{
    return new MountElement($component, $props);
}
