<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\FormDataClassNotEntityRule;

use Gamache\PHPStan\FormDataClassNotEntityRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<FormDataClassNotEntityRule>
 */
final class FormDataClassNotEntityRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new FormDataClassNotEntityRule($this->createReflectionProvider());
    }

    /** @return list<string> */
    #[\Override]
    public static function getAdditionalConfigFiles(): array
    {
        $neon = sys_get_temp_dir().'/phpstan-form-data-class-fixture.neon';
        file_put_contents($neon, sprintf(
            "parameters:\n    bootstrapFiles:\n        - %s\n",
            __DIR__.'/Fixture/violation.php',
        ));

        return array_values([...parent::getAdditionalConfigFiles(), $neon]);
    }

    public function test_dto_data_class_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_entity_data_class_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Form data_class UserEntity is a Doctrine entity. Use a DTO instead.',
                23,
            ],
        ]);
    }
}
