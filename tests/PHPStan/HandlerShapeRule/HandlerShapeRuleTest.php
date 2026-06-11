<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\HandlerShapeRule;

use Gamache\PHPStan\HandlerShapeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<HandlerShapeRule>
 */
final class HandlerShapeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new HandlerShapeRule();
    }

    public function test_valid_handler_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_invalid_handler_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Handler NotReadonlyHandler must be declared as "final readonly class".',
                7,
            ],
            [
                'Handler NotReadonlyHandler must declare exactly one public method: __invoke().',
                7,
            ],
        ]);
    }
}
