<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\IsGrantedVoterConstantRule;

use Gamache\PHPStan\IsGrantedVoterConstantRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<IsGrantedVoterConstantRule>
 */
final class IsGrantedVoterConstantRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new IsGrantedVoterConstantRule(['ROLE_', 'IS_AUTHENTICATED', 'PUBLIC_ACCESS', 'IS_IMPERSONATOR']);
    }

    public function test_constant_and_framework_role_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_string_literal_voter_attribute_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'The #[IsGranted] attribute \'edit\' must reference a Voter class constant (e.g. EventVoter::EDIT), not a string literal. Framework attributes (ROLE_, IS_AUTHENTICATED, PUBLIC_ACCESS, IS_IMPERSONATOR) are exempt.',
                9,
            ],
            [
                'The #[IsGranted] attribute \'delete\' must reference a Voter class constant (e.g. EventVoter::EDIT), not a string literal. Framework attributes (ROLE_, IS_AUTHENTICATED, PUBLIC_ACCESS, IS_IMPERSONATOR) are exempt.',
                12,
            ],
        ]);
    }
}
