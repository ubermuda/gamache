<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\RouteParamCamelCaseRule;

use Gamache\PHPStan\RouteParamCamelCaseRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<RouteParamCamelCaseRule>
 */
final class RouteParamCamelCaseRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RouteParamCamelCaseRule();
    }

    public function test_camel_case_params_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_snake_case_params_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Route parameter "org_id" must be camelCase.',
                7,
            ],
            [
                'Route parameter "project_slug" must be camelCase.',
                7,
            ],
        ]);
    }
}
