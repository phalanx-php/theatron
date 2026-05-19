<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Binding;

use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Tdom\Element\RowElement;
use Phalanx\Theatron\Tdom\Ui;

/**
 * Formats active bindings into a horizontal row of key-hint chips.
 *
 * Each chip is two Text elements separated by a space: the key combo rendered
 * in [muted] and the label rendered in [hint]. Chips are separated by two
 * spaces so the bar reads cleanly at a glance.
 *
 * Example output (styled):
 *   Ctrl+C Quit   Ctrl+1 Chat   F12 DevTools
 */
final class BindingHintsFormatter
{
    /**
     * Build a RowElement from a pre-resolved list of bindings.
     * Returns an empty row when $bindings is empty.
     *
     * @param list<Binding> $bindings
     */
    public static function render(Ui $ui, array $bindings): RowElement
    {
        if ($bindings === []) {
            return $ui->row();
        }

        $children = [];
        $last = count($bindings) - 1;

        foreach ($bindings as $i => $binding) {
            $label = $binding->label;

            // Bindings without a label are not meaningful in a hint bar.
            if ($label === null || $label === '') {
                continue;
            }

            $combo = self::formatCombo($binding);

            $children[] = $ui->text('[muted]' . $combo . '[/]');
            $children[] = $ui->text(' [hint]' . $label . '[/]');

            // Pad between chips; no trailing pad after the last one.
            if ($i < $last) {
                $children[] = $ui->text('  ');
            }
        }

        return $ui->row(...$children);
    }

    /**
     * Format a binding's key combo as a human-readable string.
     *
     * Rules:
     * - Named modifiers are capitalized: Ctrl, Alt, Shift
     * - Modifiers are joined with the key using +
     * - Key enum names are uppercased: F12, Enter, Escape
     * - Single character keys are shown as-is
     */
    public static function formatCombo(Binding $binding): string
    {
        $parts = [];

        if ($binding->ctrl) {
            $parts[] = 'Ctrl';
        }

        if ($binding->alt) {
            $parts[] = 'Alt';
        }

        if ($binding->shift) {
            $parts[] = 'Shift';
        }

        $parts[] = self::formatKey($binding->key);

        return implode('+', $parts);
    }

    private static function formatKey(Key|string $key): string
    {
        if ($key instanceof Key) {
            // F1..F12 → uppercase the raw value: "f12" → "F12"
            // Named keys → title-case the enum name: Enter, Escape, PageUp
            return match (true) {
                str_starts_with($key->value, 'f') && ctype_digit(substr($key->value, 1)) => strtoupper($key->value),
                default => $key->name,
            };
        }

        return $key;
    }
}
