<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Tests\Unit\Agent;

use Phalanx\Panoply\Effect;
use Phalanx\Panoply\Effect\Authorizer\Rules\Authorizer as RulesAuthorizer;
use Phalanx\Panoply\Effect\Decision\Verdict;
use Phalanx\Panoply\Effect\Kind;
use Phalanx\Panoply\Grant;
use Phalanx\Panoply\Hazard;
use Phalanx\Theatron\Agent\ApprovalAuthorizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApprovalAuthorizerTest extends TestCase
{
    #[Test]
    public function approvalRequiredEffectWithoutGrantPauses(): void
    {
        $authorizer = new ApprovalAuthorizer(new RulesAuthorizer());

        $decision = $authorizer->evaluate(Effect::of(
            id: 'write_file',
            kind: Kind::FileWrite,
            summary: 'Write file',
            requiresApproval: true,
        ));

        self::assertSame(Verdict::Paused, $decision->verdict);
        self::assertSame('Approval required', $decision->pauseReason);
    }

    #[Test]
    public function approvalRequiredEffectWithGrantDelegatesToRules(): void
    {
        $authorizer = new ApprovalAuthorizer(new RulesAuthorizer());
        $grant = new Grant(
            id: 'grant_1',
            subject: 'agent_1',
            allowedEffects: [Kind::FileWrite],
            scope: 'once',
            hazardCeiling: Hazard::Medium,
        );

        $decision = $authorizer->evaluate(
            Effect::of(
                id: 'write_file',
                kind: Kind::FileWrite,
                summary: 'Write file',
                requiresApproval: true,
            )->withHazard(Hazard::Medium),
            $grant,
        );

        self::assertSame(Verdict::Granted, $decision->verdict);
        self::assertSame('grant_1', $decision->grantId);
    }

    #[Test]
    public function nonApprovalEffectStillDelegatesToRules(): void
    {
        $authorizer = new ApprovalAuthorizer(new RulesAuthorizer());

        $decision = $authorizer->evaluate(Effect::of(
            id: 'read_file',
            kind: Kind::FileRead,
            summary: 'Read file',
            requiresApproval: false,
        ));

        self::assertSame(Verdict::Denied, $decision->verdict);
        self::assertSame(['no-grant'], $decision->reasonCodes);
    }
}
