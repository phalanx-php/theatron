<?php

declare(strict_types=1);

namespace Phalanx\Theatron;

use Phalanx\Boot\AppContext;
use Phalanx\Service\ServiceBundle;
use Phalanx\Service\Services;
use Phalanx\Theatron\Binding\BindingRegistry;
use Phalanx\Theatron\Stage\Stage;
use Phalanx\Theatron\Stage\StageConfig;
use Phalanx\Theatron\State\Store;
use Phalanx\Theatron\Styling\Theme;

/**
 * Registers Theatron runtime services into the Aegis container.
 *
 * Wired services:
 *   - Stage        — tick loop, rendering, input dispatch
 *   - BindingRegistry — layered key-binding resolver
 *   - Theme        — visual styling defaults
 *   - Store        — optional; only registered when storeClass is provided
 *
 * The NavigatorConfig (screen list) is intentionally not registered here
 * because the WorkspaceNavigator requires a first-screen class-string at
 * construction time, which is known only after TheatronBuilder is fully
 * configured. TheatronApp wires the navigator directly.
 */
final class TheatronServiceBundle extends ServiceBundle
{
    /**
     * @param list<class-string> $screens
     * @param class-string<Store>|null $storeClass
     */
    public function __construct(
        private(set) array $screens = [],
        private(set) ?string $storeClass = null,
        private(set) StageConfig $stageConfig = new StageConfig(),
        private(set) ?Theme $theme = null,
    ) {
    }

    public function services(Services $services, AppContext $context): void
    {
        $config = $this->stageConfig;
        $theme = $this->theme ?? Theme::default();

        $services->singleton(StageConfig::class)
            ->factory(static fn(): StageConfig => $config);

        $services->singleton(Stage::class)
            ->factory(static function () use ($config): Stage {
                return Stage::boot($config);
            });

        $services->singleton(BindingRegistry::class)
            ->factory(static fn(): BindingRegistry => new BindingRegistry());

        $services->singleton(Theme::class)
            ->factory(static fn(): Theme => $theme);

        if ($this->storeClass !== null) {
            $storeClass = $this->storeClass;
            $services->singleton($storeClass)
                ->factory(static fn(): Store => new $storeClass());
        }
    }
}
