<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template;

use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Template\Overlay\DevToolsOverlay;
use Phalanx\Theatron\Template\Screen\AgentBoardScreen;
use Phalanx\Theatron\Template\Screen\ChatScreen;
use Phalanx\Theatron\Template\Screen\SettingsScreen;
use Phalanx\Theatron\TheatronBuilder;

final class TheatronTemplate
{
    public static function configure(TheatronBuilder $builder): TheatronBuilder
    {
        return $builder
            ->store(AppStore::class)
            ->screens([
                ChatScreen::class,
                AgentBoardScreen::class,
                SettingsScreen::class,
            ])
            ->globalBindings([
                Binding::ctrl('c')->quit()->label('Quit'),
                Binding::ctrl('1')->workspace(ChatScreen::class)->label('Chat'),
                Binding::ctrl('2')->workspace(AgentBoardScreen::class)->label('Board'),
                Binding::ctrl('3')->workspace(SettingsScreen::class)->label('Settings'),
                Binding::key(Key::F12)->toggle(DevToolsOverlay::class)->label('DevTools'),
            ])
            ->devtools();
    }
}
