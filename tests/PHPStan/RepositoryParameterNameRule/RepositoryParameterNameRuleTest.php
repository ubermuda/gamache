<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\RepositoryParameterNameRule;

use Gamache\PHPStan\RepositoryParameterNameRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<RepositoryParameterNameRule>
 */
final class RepositoryParameterNameRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RepositoryParameterNameRule(['Doctrine\ORM\EntityRepository']);
    }

    public function test_pluralized_entity_nouns_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_wrong_parameter_names_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Constructor parameter $projectRepo typed ProjectRepository must be named $projects (pluralized entity noun).',
                14,
            ],
            [
                'Constructor parameter $userRepository typed UserRepository must be named $users (pluralized entity noun).',
                15,
            ],
            [
                'Constructor parameter $settingsRepo typed OrganizationInferenceSettingsRepository must be named $organizationInferenceSettings (pluralized entity noun).',
                16,
            ],
        ]);
    }
}
