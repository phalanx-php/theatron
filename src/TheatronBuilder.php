<?php

declare(strict_types=1);

namespace Phalanx\Theatron;

use InvalidArgumentException;
use Phalanx\Boot\AppContext;
use Phalanx\Service\ServiceBundle;
use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Stage\Stage;
use Phalanx\Theatron\Stage\StageConfig;
use Phalanx\Theatron\State\Store;
use Phalanx\Theatron\Styling\Theme;

/**
 * Fluent builder for Theatron applications.
 *
 * Obtain a builder via the {@see Theatron::app()} facade, configure the
 * application, then call {@see build()} to produce a {@see TheatronApp}.
 *
 * Usage:
 *
 * ```php
 * Theatron::app($context)
 *     ->store(AppStore::class)
 *     ->screens([OlympusScreen::class, SpartaScreen::class])
 *     ->globalBindings([
 *         Binding::ctrl('c')->quit(),
 *         Binding::ctrl('1')->workspace(OlympusScreen::class)->label('Olympus'),
 *     ])
 *     ->build();
 * ```
 */
final class TheatronBuilder
{
    /** @var list<class-string<Screen>> */
    private array $screens = [];

    /** @var list<Binding> */
    private array $globalBindings = [];

    /** @var list<ServiceBundle> */
    private array $extraBundles = [];

    /** @var class-string<Store>|null */
    private ?string $storeClass = null;

    private StageConfig $stageConfig;

    private ?Theme $theme = null;

    private bool $devtools = false;

    public function __construct(private(set) AppContext $context)
    {
        $this->stageConfig = new StageConfig(handleInput: true);
    }

    // -------------------------------------------------------------------------
    // Configuration

    /**
     * Register the application store. The store is constructed once and persists
     * across workspace switches (app-scope singleton).
     *
     * @param class-string<Store> $store
     */
    public function store(string $store): self
    {
        $this->storeClass = $store;

        return $this;
    }

    /**
     * Register additional Aegis service bundles.
     */
    public function services(ServiceBundle ...$bundles): self
    {
        foreach ($bundles as $bundle) {
            $this->extraBundles[] = $bundle;
        }

        return $this;
    }

    /**
     * Set the workspace (screen) registry. The first entry is the default
     * screen mounted on startup.
     *
     * @param list<class-string<Screen>> $screens
     */
    public function screens(array $screens): self
    {
        if ($screens === []) {
            throw new InvalidArgumentException('At least one screen is required.');
        }

        $this->screens = $screens;

        return $this;
    }

    /**
     * Register app-level key bindings applied regardless of the active screen.
     *
     * @param list<Binding> $bindings
     */
    public function globalBindings(array $bindings): self
    {
        $this->globalBindings = $bindings;

        return $this;
    }

    /**
     * Override the Stage rendering/input configuration.
     */
    public function stageConfig(StageConfig $config): self
    {
        $this->stageConfig = $config;

        return $this;
    }

    /**
     * Override the visual theme.
     */
    public function theme(Theme $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    /**
     * Enable the DevTools overlay.
     */
    public function devtools(bool $enabled = true): self
    {
        $this->devtools = $enabled;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Assembly

    /**
     * Build the {@see TheatronApp}.
     *
     * Validates that at least one screen is registered, then assembles the
     * Stage, Theme, and all configured options into the runtime app object.
     */
    public function build(): TheatronApp
    {
        if ($this->screens === []) {
            throw new InvalidArgumentException('At least one screen must be registered via screens().');
        }

        $theme = $this->theme ?? Theme::default();
        $stage = Stage::boot($this->stageConfig);

        return new TheatronApp(
            stage: $stage,
            theme: $theme,
            screens: $this->screens,
            globalBindings: $this->globalBindings,
            storeClass: $this->storeClass,
            devtools: $this->devtools,
        );
    }

    // -------------------------------------------------------------------------
    // Introspection

    /** @return list<ServiceBundle> */
    public function registeredBundles(): array
    {
        return $this->extraBundles;
    }

    /** @return class-string<Store>|null */
    public function registeredStore(): ?string
    {
        return $this->storeClass;
    }

    /** @return list<class-string<Screen>> */
    public function registeredScreens(): array
    {
        return $this->screens;
    }

    /** @return list<Binding> */
    public function registeredGlobalBindings(): array
    {
        return $this->globalBindings;
    }

    public function serviceBundle(): TheatronServiceBundle
    {
        return new TheatronServiceBundle(
            screens: $this->screens,
            storeClass: $this->storeClass,
            stageConfig: $this->stageConfig,
            theme: $this->theme,
        );
    }
}
