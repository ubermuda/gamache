<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\EntityAsymmetricVisibilityRule;

use Gamache\PHPStan\EntityAsymmetricVisibilityRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<EntityAsymmetricVisibilityRule>
 */
final class EntityAsymmetricVisibilityRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new EntityAsymmetricVisibilityRule($this->createReflectionProvider());
    }

    /** @return list<string> */
    #[\Override]
    public static function getAdditionalConfigFiles(): array
    {
        $neon = sys_get_temp_dir().'/phpstan-entity-asymmetric-visibility-fixture.neon';
        file_put_contents($neon, sprintf(
            "parameters:\n    bootstrapFiles:\n        - %s\n        - %s\n",
            __DIR__.'/Fixture/valid.php',
            __DIR__.'/Fixture/violation.php',
        ));

        return array_values([...parent::getAdditionalConfigFiles(), $neon]);
    }

    public function test_valid_entity_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_private_set_on_entity_property_is_reported(): void
    {
        $msg = 'Entity property $%s must not use private(set) asymmetric visibility. Use plain public visibility instead.';

        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [sprintf($msg, 'name'), 12],
            [sprintf($msg, 'email'), 13],
        ]);
    }
}
