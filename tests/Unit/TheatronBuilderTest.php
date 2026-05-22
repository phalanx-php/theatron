<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit;

use InvalidArgumentException;
use Phalanx\Boot\AppContext;
use Phalanx\Service\ServiceBundle;
use Phalanx\Service\Services;
use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Reactive\SignalRegistry;
use Phalanx\Theatron\State\Store;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Theatron;
use Phalanx\Theatron\TheatronApp;
use Phalanx\Theatron\TheatronBuilder;
use Phalanx\Theatron\TheatronRuntimeBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OlympusScreen implements Screen
{
    public function __invoke(ScreenContext $ctx): Renderable
    {
        return $ctx->ui->text('Olympus');
    }
}

final class SpartaScreen implements Screen
{
    public function __invoke(ScreenContext $ctx): Renderable
    {
        return $ctx->ui->text('Sparta');
    }
}

final class ZeusStore extends Store
{
    public function __construct()
    {
    }
}

final class AresBundle extends ServiceBundle
{
    public function services(Services $services, AppContext $context): void
    {
    }
}

final class TheatronBuilderTest extends TestCase
{
    #[Test]
    public function facadeReturnsBuilder(): void
    {
        $builder = Theatron::app([]);

        self::assertInstanceOf(TheatronBuilder::class, $builder);
    }

    #[Test]
    public function startingReturnsRuntimeBuilder(): void
    {
        self::assertInstanceOf(TheatronRuntimeBuilder::class, Theatron::starting([]));
    }

    #[Test]
    public function runtimeBuilderAcceptsProvidersDirectly(): void
    {
        $runtime = Theatron::starting([]);

        self::assertSame($runtime, $runtime->providers(new AresBundle()));
    }

    #[Test]
    public function builderHoldsContext(): void
    {
        $builder = Theatron::app(['APP_ENV' => 'test']);

        self::assertInstanceOf(AppContext::class, $builder->context);
        self::assertSame('test', $builder->context->get('APP_ENV'));
    }

    #[Test]
    public function buildRequiresAtLeastOneScreen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('screen');

        Theatron::app()->build();
    }

    #[Test]
    public function screensMethodRejectsEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Theatron::app()->screens([]);
    }

    #[Test]
    public function buildProducesTheatronApp(): void
    {
        $app = Theatron::app()
            ->screens([OlympusScreen::class])
            ->build();

        self::assertInstanceOf(TheatronApp::class, $app);
    }

    #[Test]
    public function defaultScreenIsFirstInList(): void
    {
        $app = Theatron::app()
            ->screens([OlympusScreen::class, SpartaScreen::class])
            ->build();

        self::assertSame(OlympusScreen::class, $app->screens()[0]);
    }

    #[Test]
    public function allRegisteredScreensArePreserved(): void
    {
        $app = Theatron::app()
            ->screens([OlympusScreen::class, SpartaScreen::class])
            ->build();

        self::assertSame([OlympusScreen::class, SpartaScreen::class], $app->screens());
    }

    #[Test]
    public function globalBindingsArePassedToApp(): void
    {
        $binding = Binding::ctrl('c')->quit();

        $app = Theatron::app()
            ->screens([OlympusScreen::class])
            ->globalBindings([$binding])
            ->build();

        self::assertCount(1, $app->globalBindings());
        self::assertSame($binding, $app->globalBindings()[0]);
    }

    #[Test]
    public function noBindingsByDefault(): void
    {
        $app = Theatron::app()
            ->screens([OlympusScreen::class])
            ->build();

        self::assertSame([], $app->globalBindings());
    }

    #[Test]
    public function storeClassIsPassedToApp(): void
    {
        $app = Theatron::app()
            ->screens([OlympusScreen::class])
            ->store(ZeusStore::class)
            ->build();

        self::assertSame(ZeusStore::class, $app->storeClass);
    }

    #[Test]
    public function noStoreByDefault(): void
    {
        $app = Theatron::app()
            ->screens([OlympusScreen::class])
            ->build();

        self::assertNull($app->storeClass);
    }

    #[Test]
    public function devtoolsDefaultsToFalse(): void
    {
        $app = Theatron::app()
            ->screens([OlympusScreen::class])
            ->build();

        self::assertFalse($app->devtools);
    }

    #[Test]
    public function devtoolsCanBeEnabled(): void
    {
        $app = Theatron::app()
            ->screens([OlympusScreen::class])
            ->devtools()
            ->build();

        self::assertTrue($app->devtools);
    }

    #[Test]
    public function devtoolsBuildCreatesSignalRegistry(): void
    {
        $app = Theatron::app()
            ->screens([OlympusScreen::class])
            ->devtools()
            ->build();

        self::assertInstanceOf(SignalRegistry::class, $app->registry);
    }

    #[Test]
    public function buildWithoutDevtoolsHasNullRegistry(): void
    {
        $app = Theatron::app()
            ->screens([OlympusScreen::class])
            ->build();

        self::assertNull($app->registry);
    }

    #[Test]
    public function builderIsFluentChainable(): void
    {
        $builder = Theatron::app();
        $returned = $builder->screens([OlympusScreen::class]);

        self::assertSame($builder, $returned);
    }

    #[Test]
    public function registeredScreensIntrospection(): void
    {
        $builder = Theatron::app()
            ->screens([OlympusScreen::class, SpartaScreen::class]);

        self::assertSame([OlympusScreen::class, SpartaScreen::class], $builder->registeredScreens());
    }

    #[Test]
    public function registeredStoreIntrospection(): void
    {
        $builder = Theatron::app()
            ->screens([OlympusScreen::class])
            ->store(ZeusStore::class);

        self::assertSame(ZeusStore::class, $builder->registeredStore());
    }

    #[Test]
    public function registeredGlobalBindingsIntrospection(): void
    {
        $binding = Binding::ctrl('q')->quit();
        $builder = Theatron::app()
            ->screens([OlympusScreen::class])
            ->globalBindings([$binding]);

        self::assertSame([$binding], $builder->registeredGlobalBindings());
    }

    #[Test]
    public function multipleBindingsAllPreserved(): void
    {
        $quit = Binding::ctrl('c')->quit();
        $switch = Binding::ctrl('1')->workspace(SpartaScreen::class)->label('Sparta');

        $app = Theatron::app()
            ->screens([OlympusScreen::class, SpartaScreen::class])
            ->globalBindings([$quit, $switch])
            ->build();

        self::assertCount(2, $app->globalBindings());
    }
}
