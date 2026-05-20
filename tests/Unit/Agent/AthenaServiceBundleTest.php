<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Agent;

use Phalanx\Athena\AthenaBundle;
use Phalanx\Athena\Router\InvocationRouter;
use Phalanx\Boot\AppContext;
use Phalanx\Service\ServiceBundle;
use Phalanx\Service\ServiceConfig;
use Phalanx\Service\Services;
use Phalanx\Theatron\Agent\AthenaServiceBundle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AthenaServiceBundleTest extends TestCase
{
    #[Test]
    public function fromCreatesBundle(): void
    {
        $athenaBundle = self::makeAthenaBundle();

        $bundle = AthenaServiceBundle::from($athenaBundle);

        self::assertInstanceOf(AthenaServiceBundle::class, $bundle);
        self::assertInstanceOf(ServiceBundle::class, $bundle);
    }

    #[Test]
    public function fromHoldsDelegate(): void
    {
        $athenaBundle = self::makeAthenaBundle();

        $bundle = AthenaServiceBundle::from($athenaBundle);

        self::assertSame($athenaBundle, $bundle->athenaBundle);
    }

    #[Test]
    public function delegatesToAthenaBundleOnServices(): void
    {
        $athenaBundle = self::makeAthenaBundle();
        $bundle = AthenaServiceBundle::from($athenaBundle);

        $serviceConfig = $this->createStub(ServiceConfig::class);
        $serviceConfig->method('factory')->willReturnSelf();
        $serviceConfig->method('needs')->willReturnSelf();

        $services = $this->createMock(Services::class);
        $services->method('singleton')->willReturn($serviceConfig);
        $services->method('scoped')->willReturn($serviceConfig);
        $services->method('has')->willReturn(false);

        $services->expects(self::atLeastOnce())->method('singleton');

        $context = new AppContext([]);

        $bundle->services($services, $context);
    }

    private static function makeAthenaBundle(): AthenaBundle
    {
        $router = new class implements InvocationRouter {
            public function route(
                \Phalanx\Scope\TaskScope $scope,
                \Phalanx\Panoply\Agent $agent,
                \Phalanx\Panoply\Invocation $invocation,
            ): \Phalanx\Panoply\Provider {
                throw new \LogicException('Not implemented in test stub.');
            }
        };

        return new AthenaBundle($router);
    }
}
