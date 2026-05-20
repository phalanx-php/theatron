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

final class TheatronBuilder
{
    /** @var list<class-string<Screen>> */
    private array $screens = [];

    /** @var list<Binding> */
    private array $globalBindings = [];

    /** @var list<ServiceBundle> */
    private array $bundles = [];

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

    /** @param class-string<Store> $store */
    public function store(string $store): self
    {
        $this->storeClass = $store;

        return $this;
    }

    /** @param list<class-string<Screen>> $screens */
    public function screens(array $screens): self
    {
        if ($screens === []) {
            throw new InvalidArgumentException('At least one screen is required.');
        }

        $this->screens = $screens;

        return $this;
    }

    /** @param list<Binding> $bindings */
    public function globalBindings(array $bindings): self
    {
        $this->globalBindings = $bindings;

        return $this;
    }

    public function stageConfig(StageConfig $config): self
    {
        $this->stageConfig = $config;

        return $this;
    }

    public function theme(Theme $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function devtools(bool $enabled = true): self
    {
        $this->devtools = $enabled;

        return $this;
    }

    public function services(ServiceBundle ...$bundles): self
    {
        $this->bundles = [...$this->bundles, ...array_values($bundles)];

        return $this;
    }

    // -------------------------------------------------------------------------
    // Assembly

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

    /** @return list<ServiceBundle> */
    public function registeredServiceBundles(): array
    {
        return $this->bundles;
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
