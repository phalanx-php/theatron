<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Agent;

use Phalanx\Athena\AthenaBundle;
use Phalanx\Boot\AppContext;
use Phalanx\Service\ServiceBundle;
use Phalanx\Service\Services;

final class AthenaServiceBundle extends ServiceBundle
{
    public function __construct(
        private(set) AthenaBundle $athenaBundle,
    ) {
    }

    public static function from(AthenaBundle $bundle): self
    {
        return new self($bundle);
    }

    public function services(Services $services, AppContext $context): void
    {
        $bundle = new AthenaBundle(
            router: new LlmRequestRecordingRouter($this->athenaBundle->router),
            toolBundles: $this->athenaBundle->toolBundles,
            mcpServers: $this->athenaBundle->mcpServers,
            hooks: $this->athenaBundle->hooks,
        );

        $bundle->services($services, $context);
    }
}
