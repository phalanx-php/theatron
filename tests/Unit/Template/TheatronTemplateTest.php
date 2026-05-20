<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template;

use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Overlay\DevToolsOverlay;
use Phalanx\Theatron\Template\Screen\AgentBoardScreen;
use Phalanx\Theatron\Template\Screen\ChatScreen;
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
    public function configureRegistersThreeScreens(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());

        self::assertSame(
            [ChatScreen::class, AgentBoardScreen::class, SettingsScreen::class],
            $builder->registeredScreens(),
        );
    }

    #[Test]
    public function configureRegistersFiveGlobalBindings(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());

        self::assertCount(5, $builder->registeredGlobalBindings());
    }

    #[Test]
    public function firstBindingIsQuit(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());
        $bindings = $builder->registeredGlobalBindings();

        self::assertInstanceOf(Binding::class, $bindings[0]);
        self::assertTrue($bindings[0]->action?->isQuit());
        self::assertSame('Quit', $bindings[0]->label);
    }

    #[Test]
    public function workspaceBindingsTargetCorrectScreens(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());
        $bindings = $builder->registeredGlobalBindings();

        self::assertTrue($bindings[1]->action?->isWorkspace());
        self::assertSame(ChatScreen::class, $bindings[1]->action->target);

        self::assertTrue($bindings[2]->action?->isWorkspace());
        self::assertSame(AgentBoardScreen::class, $bindings[2]->action->target);

        self::assertTrue($bindings[3]->action?->isWorkspace());
        self::assertSame(SettingsScreen::class, $bindings[3]->action->target);
    }

    #[Test]
    public function devtoolsBindingTogglesDevToolsOverlay(): void
    {
        $builder = TheatronTemplate::configure(Theatron::app());
        $bindings = $builder->registeredGlobalBindings();

        self::assertTrue($bindings[4]->action?->isToggle());
        self::assertSame(DevToolsOverlay::class, $bindings[4]->action->target);
        self::assertSame('DevTools', $bindings[4]->label);
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
