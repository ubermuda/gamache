<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\RouteParamSnakeCaseRule;

use Gamache\PHPStan\RouteParamSnakeCaseRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<RouteParamSnakeCaseRule>
 */
final class RouteParamSnakeCaseRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RouteParamSnakeCaseRule();
    }

    public function test_snake_case_params_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_camel_case_params_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Route parameter "orgId" must be snake_case.',
                7,
            ],
            [
                'Route parameter "projectSlug" must be snake_case.',
                7,
            ],
        ]);
    }
}
