<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\EnumKebabCaseRule;

use Gamache\PHPStan\EnumKebabCaseRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<EnumKebabCaseRule>
 */
final class EnumKebabCaseRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new EnumKebabCaseRule();
    }

    public function test_valid_kebab_cases_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_non_kebab_case_values_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Enum case value "Active" must be kebab-case (e.g. "my-value").',
                7,
            ],
            [
                'Enum case value "in_progress" must be kebab-case (e.g. "my-value").',
                8,
            ],
        ]);
    }
}
