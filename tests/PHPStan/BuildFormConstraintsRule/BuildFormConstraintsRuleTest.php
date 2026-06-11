<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\BuildFormConstraintsRule;

use Gamache\PHPStan\BuildFormConstraintsRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<BuildFormConstraintsRule>
 */
final class BuildFormConstraintsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BuildFormConstraintsRule();
    }

    public function test_no_inline_constraints_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_inline_constraints_without_data_class_passes(): void
    {
        // Forms without data_class have no DTO to move constraints to — rule should not fire.
        $this->analyse([__DIR__.'/Fixture/violation.php'], []);
    }

    public function test_inline_constraints_with_data_class_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation_with_data_class.php'], [
            [
                'Form constraints must be declared on the DTO class, not inline in buildForm().',
                17,
            ],
        ]);
    }

    public function test_no_inline_constraints_with_data_class_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid_with_data_class.php'], []);
    }
}
