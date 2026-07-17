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
    private const string MESSAGE = 'Method WithPassThroughHelpers::%s() is a one-liner pass-through to $this->%s — inline the call at its call sites.';

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
            [sprintf(self::MESSAGE, 'buildMatrix', 'matrixBuilder->build()'), 16],
            [sprintf(self::MESSAGE, 'flush', 'matrixBuilder->flush()'), 21],
            [sprintf(self::MESSAGE, 'protectedForward', 'matrixBuilder->build()'), 26],
            [sprintf(self::MESSAGE, 'variadicForward', 'matrixBuilder->sum()'), 31],
            [sprintf(self::MESSAGE, 'forwardsProperty', 'matrixBuilder->build()'), 36],
            [sprintf(self::MESSAGE, 'reordersArguments', 'matrixBuilder->pair()'), 41],
            [sprintf(self::MESSAGE, 'dropsParameter', 'matrixBuilder->build()'), 46],
            [sprintf(self::MESSAGE, 'usesNamedArgument', 'matrixBuilder->build()'), 51],
            [sprintf(self::MESSAGE, 'callsNonPromotedProperty', 'lazy->build()'), 56],
            [sprintf(self::MESSAGE, 'callsThroughChain', 'lazy->inner->build()'), 61],
            [sprintf(self::MESSAGE, 'callsAccessorInChain', 'matrixBuilder->inner()->build()'), 66],
            [sprintf(self::MESSAGE, 'delegatesToSibling', 'reshape()'), 71],
        ]);
    }
}
