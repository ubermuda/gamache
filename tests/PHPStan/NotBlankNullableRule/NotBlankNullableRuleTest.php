<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\NotBlankNullableRule;

use Gamache\PHPStan\NotBlankNullableRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NotBlankNullableRule>
 */
final class NotBlankNullableRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NotBlankNullableRule();
    }

    public function test_nullable_param_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_non_nullable_param_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Promoted property $name has #[NotBlank] but is not nullable. Use ?string or string|null.',
                10,
            ],
        ]);
    }
}
