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

    public function test_inline_constraints_without_data_class_are_reported(): void
    {
        // Unmapped forms must introduce a Request DTO instead of inline constraints.
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Form constraints must be declared on the DTO class (introduce a Request DTO for unmapped forms), not inline in buildForm().',
                16,
            ],
        ]);
    }

    public function test_inline_constraints_with_data_class_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation_with_data_class.php'], [
            [
                'Form constraints must be declared on the DTO class (introduce a Request DTO for unmapped forms), not inline in buildForm().',
                17,
            ],
        ]);
    }

    public function test_no_inline_constraints_with_data_class_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid_with_data_class.php'], []);
    }

    public function test_constraints_key_outside_builder_options_passes(): void
    {
        // A 'constraints' array key that is not part of an add()/create() options
        // argument (e.g. view variables) is not a form constraint declaration.
        $this->analyse([__DIR__.'/Fixture/valid_constraints_key_outside_add.php'], []);
    }

    public function test_constraints_in_nested_entry_options_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation_entry_options.php'], [
            [
                'Form constraints must be declared on the DTO class (introduce a Request DTO for unmapped forms), not inline in buildForm().',
                17,
            ],
        ]);
    }
}
