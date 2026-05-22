<?php

declare(strict_types=1);

namespace Phalanx\Theatron;

use Phalanx\Application;
use Phalanx\ApplicationBuilder;
use Phalanx\Boot\AppContext;
use Phalanx\Scope\ExecutionScope;
use Phalanx\Service\ServiceBundle;

final class TheatronRuntimeBuilder
{
    private ApplicationBuilder $app;

    public function __construct(AppContext $context)
    {
        $this->app = Application::starting($context->values);
    }

    public function providers(ServiceBundle ...$providers): self
    {
        $this->app->providers(...$providers);

        return $this;
    }

    public function run(TheatronApp $app): int
    {
        $this->app->run(static function (ExecutionScope $scope) use ($app): void {
            $app->start($scope);
        });

        return 0;
    }
}
