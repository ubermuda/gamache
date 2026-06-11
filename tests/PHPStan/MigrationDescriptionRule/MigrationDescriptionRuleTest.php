<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\MigrationDescriptionRule;

use Gamache\PHPStan\MigrationDescriptionRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<MigrationDescriptionRule>
 */
final class MigrationDescriptionRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new MigrationDescriptionRule();
    }

    public function test_valid_description_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_empty_description_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Migration::getDescription() must return a non-empty string literal.',
                13,
            ],
        ]);
    }
}
