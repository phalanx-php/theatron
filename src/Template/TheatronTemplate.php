<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template;

use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Template\Screen\AgentBoardScreen;
use Phalanx\Theatron\Template\Screen\ChatScreen;
use Phalanx\Theatron\Template\Screen\DevToolsScreen;
use Phalanx\Theatron\Template\Screen\LlmRequestDetailScreen;
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
                DevToolsScreen::class,
                LlmRequestDetailScreen::class,
                SettingsScreen::class,
            ])
            ->globalBindings([
                Binding::ctrl('c')->quit()->label('quit'),
                Binding::ctrl('d')->workspace(DevToolsScreen::class)->label('devtools'),
                Binding::ctrl('s')->workspace(SettingsScreen::class)->label('settings'),
            ])
            ->devtools();
    }
}
