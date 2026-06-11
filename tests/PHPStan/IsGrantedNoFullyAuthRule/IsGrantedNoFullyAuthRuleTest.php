<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\IsGrantedNoFullyAuthRule;

use Gamache\PHPStan\IsGrantedNoFullyAuthRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<IsGrantedNoFullyAuthRule>
 */
final class IsGrantedNoFullyAuthRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new IsGrantedNoFullyAuthRule();
    }

    public function test_voter_constant_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_is_authenticated_fully_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                '#[IsGranted(\'IS_AUTHENTICATED_FULLY\')] bypasses Voter-based ownership checks. Specify a Voter constant and a subject instead.',
                7,
            ],
            [
                '#[IsGranted(\'IS_AUTHENTICATED_FULLY\')] bypasses Voter-based ownership checks. Specify a Voter constant and a subject instead.',
                12,
            ],
        ]);
    }
}
