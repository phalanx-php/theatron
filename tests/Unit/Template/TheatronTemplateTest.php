<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template;

use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Screen\AgentBoardScreen;
use Phalanx\Theatron\Template\Screen\ChatScreen;
use Phalanx\Theatron\Template\Screen\DevToolsScreen;
use Phalanx\Theatron\Template\Screen\LlmRequestDetailScreen;
use Phalanx\Theatron\Template\Screen\SettingsScreen;
use Phalanx\Theatron\Template\TheatronTemplate;
use Phalanx\Theatron\Theatron;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TheatronTemplateTest extends TestCase
{
    #[Test]
    public function configureRegistersAppStore(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());

        self::assertSame(AppStore::class, $builder->registeredStore());
    }

    #[Test]
    public function configureRegistersReplParityScreens(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());

        self::assertSame(
            [
                ChatScreen::class,
                AgentBoardScreen::class,
                DevToolsScreen::class,
                LlmRequestDetailScreen::class,
                SettingsScreen::class,
            ],
            $builder->registeredScreens(),
        );
    }

    #[Test]
    public function configureRegistersReplGlobalBindings(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());

        self::assertCount(3, $builder->registeredGlobalBindings());
    }

    #[Test]
    public function firstBindingIsQuit(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());
        $bindings = $builder->registeredGlobalBindings();

        self::assertInstanceOf(Binding::class, $bindings[0]);
        self::assertTrue($bindings[0]->action?->isQuit());
        self::assertSame('quit', $bindings[0]->label);
    }

    #[Test]
    public function workspaceBindingsTargetFullPageScreens(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());
        $bindings = $builder->registeredGlobalBindings();

        self::assertTrue($bindings[1]->action?->isWorkspace());
        self::assertSame(DevToolsScreen::class, $bindings[1]->action->target);
        self::assertSame('devtools', $bindings[1]->label);

        self::assertTrue($bindings[2]->action?->isWorkspace());
        self::assertSame(SettingsScreen::class, $bindings[2]->action->target);
        self::assertSame('settings', $bindings[2]->label);
    }

    #[Test]
    public function devtoolsIsEnabled(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());
        $app = $builder->build();

        self::assertTrue($app->devtools);
    }

    #[Test]
    public function configureReturnsSameBuilderInstance(): void
    {
        $builder = Theatron::app();
        $returned = TheatronTemplate::configure($builder);

        self::assertSame($builder, $returned);
    }
}
