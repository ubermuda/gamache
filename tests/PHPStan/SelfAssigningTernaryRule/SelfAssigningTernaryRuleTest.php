<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\SelfAssigningTernaryRule;

use Gamache\PHPStan\SelfAssigningTernaryRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<SelfAssigningTernaryRule>
 */
final class SelfAssigningTernaryRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new SelfAssigningTernaryRule();
    }

    public function test_self_assigning_ternaries_are_reported(): void
    {
        $message = 'Self-assigning ternary: one branch assigns the target back to itself; rewrite as an `if` that assigns only on the branch that changes state.';
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [$message, 9],
            [$message, 10],
            [$message, 13],
        ]);
    }

    public function test_legitimate_patterns_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }
}
