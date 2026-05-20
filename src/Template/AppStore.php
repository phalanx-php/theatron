<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Template;

use Phalanx\Theatron\Input\InputModeSlice;
use Phalanx\Theatron\State\Store;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\AgentRegistrySlice;
use Phalanx\Theatron\Template\Slice\ConversationSlice;

class AppStore extends Store
{
    public ConversationSlice $conversation {
        get => $this->read(ConversationSlice::class);
        set {
            $this->write(ConversationSlice::class, $value);
        }
    }

    public AgentRegistrySlice $agents {
        get => $this->read(AgentRegistrySlice::class);
        set {
            $this->write(AgentRegistrySlice::class, $value);
        }
    }

    public ActivitySlice $activity {
        get => $this->read(ActivitySlice::class);
        set {
            $this->write(ActivitySlice::class, $value);
        }
    }

    public InputModeSlice $inputMode {
        get => $this->read(InputModeSlice::class);
        set {
            $this->write(InputModeSlice::class, $value);
        }
    }

    public function __construct()
    {
        $this->register(ConversationSlice::class, new ConversationSlice());
        $this->register(AgentRegistrySlice::class, new AgentRegistrySlice());
        $this->register(ActivitySlice::class, new ActivitySlice());
        $this->register(InputModeSlice::class, new InputModeSlice());
    }
}
