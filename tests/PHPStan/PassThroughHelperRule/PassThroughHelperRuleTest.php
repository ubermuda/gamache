<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\PassThroughHelperRule;

use Gamache\PHPStan\PassThroughHelperRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<PassThroughHelperRule>
 */
final class PassThroughHelperRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new PassThroughHelperRule();
    }

    public function test_helpers_with_real_logic_never_match(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_pass_through_helpers_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Method WithPassThroughHelpers::buildMatrix() is a one-liner pass-through to $this->matrixBuilder->build() — inline the call at its call sites.',
                22,
            ],
            [
                'Method WithPassThroughHelpers::flush() is a one-liner pass-through to $this->matrixBuilder->flush() — inline the call at its call sites.',
                27,
            ],
            [
                'Method WithPassThroughHelpers::protectedForward() is a one-liner pass-through to $this->matrixBuilder->build() — inline the call at its call sites.',
                32,
            ],
        ]);
    }
}
