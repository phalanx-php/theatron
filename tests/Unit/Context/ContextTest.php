<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Context;

use Phalanx\Theatron\Component\MountedComponent;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    #[Test]
    public function renderContextMountDelegatesToMountSystem(): void
    {
        $scope = $this->createStub(\Phalanx\Scope\Scope::class);
        $mount = new MountSystem($scope);
        $ctx = new RenderContext($scope, new Ui(), Theme::default(), $mount);

        $mounted = $ctx->mount(ContextTestComponent::class, label: 'Zeus');

        self::assertInstanceOf(MountedComponent::class, $mounted);

        $result = $mounted->render($ctx);
        self::assertInstanceOf(Renderable::class, $result);
    }

    #[Test]
    public function screenContextMountDelegatesToMountSystem(): void
    {
        $scope = $this->createStub(\Phalanx\Scope\TaskScope::class);
        $navigator = $this->createStub(Navigator::class);
        $mount = new MountSystem($scope);
        $ctx = new ScreenContext($scope, new Ui(), Theme::default(), $navigator, $mount);

        $mounted = $ctx->mount(ContextTestComponent::class, label: 'Poseidon');

        self::assertInstanceOf(MountedComponent::class, $mounted);
    }
}

final class ContextTestComponent implements Component
{
    public function __construct(
        private(set) string $label = 'default',
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text($this->label);
    }
}
