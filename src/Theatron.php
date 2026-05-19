<?php

declare(strict_types=1);

namespace Phalanx\Theatron;

use Phalanx\Boot\AppContext;

/**
 * Entry-point facade for Theatron applications.
 *
 * Bootstraps a fluent {@see TheatronBuilder} from the symfony/runtime context
 * array. Entry point files should call this from the outer closure returned to
 * autoload_runtime.php.
 *
 * Usage:
 *
 * ```php
 * return static function (array $context): Closure {
 *     return Theatron::app($context)
 *         ->screens([ChatScreen::class, SettingsScreen::class])
 *         ->globalBindings([Binding::ctrl('c')->quit()])
 *         ->build()
 *         ->start(...);
 * };
 * ```
 */
final class Theatron
{
    /**
     * Create a builder from the symfony/runtime context array.
     *
     * @param array<string,mixed> $context
     */
    public static function app(array $context = []): TheatronBuilder
    {
        return new TheatronBuilder(new AppContext($context));
    }
}
