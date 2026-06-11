<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\VoterNotReadonlyRule;

use Gamache\PHPStan\VoterNotReadonlyRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<VoterNotReadonlyRule>
 */
final class VoterNotReadonlyRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new VoterNotReadonlyRule($this->createReflectionProvider());
    }

    public function test_non_readonly_voter_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_readonly_voter_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Voter ReadonlyVoter must not be readonly. Use "final class", not "final readonly class".',
                9,
            ],
        ]);
    }
}
