<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Navigation;

interface Navigator
{
    public function go(string $screen): void;
    public function overlay(string $component, mixed ...$params): void;
    public function dismiss(): void;
    public function dismissAll(): void;
    public function active(): string;
}
