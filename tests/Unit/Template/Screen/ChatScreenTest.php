<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Template\Screen;

use Phalanx\Scope\TaskScope;
use Phalanx\Theatron\Component\MountSystem;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Navigation\Navigator;
use Phalanx\Theatron\Styling\Theme;
use Phalanx\Theatron\Tdom\Element\ColumnElement;
use Phalanx\Theatron\Tdom\Element\DividerElement;
use Phalanx\Theatron\Tdom\Element\InputElement;
use Phalanx\Theatron\Tdom\Element\ScrollElement;
use Phalanx\Theatron\Tdom\Element\SpinnerElement;
use Phalanx\Theatron\Tdom\Element\StatusLineElement;
use Phalanx\Theatron\Tdom\Element\TextElement;
use Phalanx\Theatron\Tdom\Ui;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Screen\ChatScreen;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\ActivityStatus;
use Phalanx\Theatron\Template\Slice\ConversationSlice;
use Phalanx\Theatron\Template\Slice\PendingEffect;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChatScreenTest extends TestCase
{
    #[Test]
    public function rendersEmptyConversation(): void
    {
        $store = new AppStore();
        $screen = new ChatScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);

        self::assertInstanceOf(ColumnElement::class, $result);

        $children = $result->children;
        self::assertCount(5, $children);

        self::assertInstanceOf(ScrollElement::class, $children[0]);
        self::assertInstanceOf(TextElement::class, $children[1]);
        self::assertInstanceOf(DividerElement::class, $children[2]);
        self::assertInstanceOf(InputElement::class, $children[3]);
        self::assertInstanceOf(StatusLineElement::class, $children[4]);
    }

    #[Test]
    public function rendersMessages(): void
    {
        $store = new AppStore();
        $store->conversation = new ConversationSlice()
            ->addUserMessage('Advance the phalanx, Leonidas commands it.')
            ->appendToken('By Zeus, we hold the pass at Thermopylae.');

        $screen = new ChatScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);
        self::assertInstanceOf(ColumnElement::class, $result);

        $scroll = $result->children[0];
        self::assertInstanceOf(ScrollElement::class, $scroll);
        self::assertStringContainsString('Advance the phalanx', $scroll->content);
        self::assertStringContainsString('Thermopylae', $scroll->content);
        self::assertStringContainsString('You:', $scroll->content);
        self::assertStringContainsString('Assistant:', $scroll->content);
    }

    #[Test]
    public function showsSpinnerWhenStreaming(): void
    {
        $store = new AppStore();
        $store->conversation = new ConversationSlice()
            ->addUserMessage('What is the will of Apollo?')
            ->appendToken('The oracle speaks...');

        self::assertTrue($store->conversation->isStreaming);

        $screen = new ChatScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);
        self::assertInstanceOf(ColumnElement::class, $result);

        $indicator = $result->children[1];
        self::assertInstanceOf(SpinnerElement::class, $indicator);
        self::assertSame('Streaming...', $indicator->label);
    }

    #[Test]
    public function hidesSpinnerWhenNotStreaming(): void
    {
        $store = new AppStore();

        self::assertFalse($store->conversation->isStreaming);

        $screen = new ChatScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);
        self::assertInstanceOf(ColumnElement::class, $result);

        $indicator = $result->children[1];
        self::assertInstanceOf(TextElement::class, $indicator);
    }

    #[Test]
    public function showsStatusWithTokenCounts(): void
    {
        $store = new AppStore();
        $store->activity = new ActivitySlice()->updateUsage(400, 1200);

        $screen = new ChatScreen($store);
        $ctx = $this->makeContext($store);

        $result = $screen($ctx);
        self::assertInstanceOf(ColumnElement::class, $result);

        $statusLine = $result->children[4];
        self::assertInstanceOf(StatusLineElement::class, $statusLine);
        self::assertCount(2, $statusLine->sections);

        $tokenText = $statusLine->sections[1];
        self::assertInstanceOf(TextElement::class, $tokenText);

        $content = $tokenText->content;
        self::assertIsString($content);
        self::assertStringContainsString('400', $content);
        self::assertStringContainsString('1200', $content);
        self::assertStringContainsString('1600', $content);
    }

    #[Test]
    public function rendersActivityStatus(): void
    {
        $cases = [
            [ActivityStatus::Idle, 'Idle'],
            [ActivityStatus::Running, 'Running'],
            [ActivityStatus::AwaitingApproval, 'Awaiting Approval'],
            [ActivityStatus::Completed, 'Completed'],
            [ActivityStatus::Failed, 'Failed'],
            [ActivityStatus::Cancelled, 'Cancelled'],
        ];

        foreach ($cases as [$status, $expectedLabel]) {
            $store = new AppStore();

            if ($status === ActivityStatus::Idle) {
                // Default state — no write needed
            } elseif (
                $status === ActivityStatus::Completed
                || $status === ActivityStatus::Failed
                || $status === ActivityStatus::Cancelled
            ) {
                $store->activity = new ActivitySlice()->activityEnded($status);
            } elseif ($status === ActivityStatus::AwaitingApproval) {
                $store->activity = new ActivitySlice()->awaitingApproval(
                    new PendingEffect(
                        kind: 'read_file',
                        summary: 'Read /etc/hosts',
                        arguments: [],
                        hazardLevel: 1,
                    ),
                );
            } else {
                $store->activity = new ActivitySlice()->effectResolved();
            }

            $screen = new ChatScreen($store);
            $ctx = $this->makeContext($store);

            $result = $screen($ctx);
            self::assertInstanceOf(ColumnElement::class, $result);

            $statusLine = $result->children[4];
            self::assertInstanceOf(StatusLineElement::class, $statusLine);

            $labelElement = $statusLine->sections[0];
            self::assertInstanceOf(TextElement::class, $labelElement);

            $content = $labelElement->content;
            self::assertIsString($content);
            self::assertSame(
                $expectedLabel,
                $content,
                sprintf('Expected label "%s" for status %s', $expectedLabel, $status->name),
            );
        }
    }

    private function makeContext(AppStore $store): ScreenContext
    {
        $scope = $this->createStub(TaskScope::class);
        $navigator = $this->createStub(Navigator::class);
        $mountSystem = new MountSystem($scope);

        return new ScreenContext($scope, new Ui(), Theme::default(), $navigator, $mountSystem);
    }
}
