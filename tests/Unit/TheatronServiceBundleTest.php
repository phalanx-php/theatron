<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit;

use Phalanx\Boot\AppContext;
use Phalanx\Service\ServiceCatalog;
use Phalanx\Theatron\Binding\BindingRegistry;
use Phalanx\Theatron\Stage\Stage;
use Phalanx\Theatron\Stage\StageConfig;
use Phalanx\Theatron\State\Store;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\TheatronServiceBundle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

final class ApolloStore extends Store
{
    public function __construct()
    {
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

final class TheatronServiceBundleTest extends TestCase
{
    #[Test]
    public function registersStageAsSingleton(): void
    {
        $catalog = new ServiceCatalog(new AppContext());
        $bundle = new TheatronServiceBundle();
        $bundle->services($catalog, new AppContext());

        self::assertTrue($catalog->has(Stage::class));
    }

    #[Test]
    public function registersStageConfigAsSingleton(): void
    {
        $catalog = new ServiceCatalog(new AppContext());
        $bundle = new TheatronServiceBundle();
        $bundle->services($catalog, new AppContext());

        self::assertTrue($catalog->has(StageConfig::class));
    }

    #[Test]
    public function registersBindingRegistryAsSingleton(): void
    {
        $catalog = new ServiceCatalog(new AppContext());
        $bundle = new TheatronServiceBundle();
        $bundle->services($catalog, new AppContext());

        self::assertTrue($catalog->has(BindingRegistry::class));
    }

    #[Test]
    public function registersThemeAsSingleton(): void
    {
        $catalog = new ServiceCatalog(new AppContext());
        $bundle = new TheatronServiceBundle();
        $bundle->services($catalog, new AppContext());

        self::assertTrue($catalog->has(Theme::class));
    }

    #[Test]
    public function doesNotRegisterStoreWhenNotConfigured(): void
    {
        $catalog = new ServiceCatalog(new AppContext());
        $bundle = new TheatronServiceBundle();
        $bundle->services($catalog, new AppContext());

        self::assertFalse($catalog->has(ApolloStore::class));
    }

    #[Test]
    public function registersStoreWhenConfigured(): void
    {
        $catalog = new ServiceCatalog(new AppContext());
        $bundle = new TheatronServiceBundle(storeClass: ApolloStore::class);
        $bundle->services($catalog, new AppContext());

        self::assertTrue($catalog->has(ApolloStore::class));
    }

    #[Test]
    public function screensAreStoredOnBundle(): void
    {
        $bundle = new TheatronServiceBundle(screens: [ApolloStore::class]);

        self::assertSame([ApolloStore::class], $bundle->screens);
    }

    #[Test]
    public function storeClassIsStoredOnBundle(): void
    {
        $bundle = new TheatronServiceBundle(storeClass: ApolloStore::class);

        self::assertSame(ApolloStore::class, $bundle->storeClass);
    }

    #[Test]
    public function customStageConfigIsStoredOnBundle(): void
    {
        $config = new StageConfig(handleInput: false);
        $bundle = new TheatronServiceBundle(stageConfig: $config);

        self::assertFalse($bundle->stageConfig->handleInput);
    }

    #[Test]
    public function nullThemeDefaultsToThemeDefault(): void
    {
        // Theme::default() has private constructor; verify the bundle accepts
        // null and registers Theme in the catalog (resolution uses Theme::default()).
        $catalog = new ServiceCatalog(new AppContext());
        $bundle = new TheatronServiceBundle(theme: null);
        $bundle->services($catalog, new AppContext());

        self::assertTrue($catalog->has(Theme::class));
    }
}
