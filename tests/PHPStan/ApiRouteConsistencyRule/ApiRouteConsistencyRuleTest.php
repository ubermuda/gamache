<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\ApiRouteConsistencyRule;

use Gamache\PHPStan\ApiRouteConsistencyRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ApiRouteConsistencyRule>
 */
final class ApiRouteConsistencyRuleTest extends RuleTestCase
{
    private const string MSG = 'API routing convention mismatch on %s: path "%s", name %s, and namespace %s must agree. An "/api/" path requires an "api_" route name and a "\\Controller\\Api\\" namespace (and vice versa).';

    protected function getRule(): Rule
    {
        return new ApiRouteConsistencyRule();
    }

    public function test_agreeing_routes_pass(): void
    {
        $this->analyse([
            __DIR__.'/Fixture/valid_api.php',
            __DIR__.'/Fixture/valid_web.php',
        ], []);
    }

    public function test_mismatched_routes_are_reported(): void
    {
        $this->analyse([
            __DIR__.'/Fixture/violation_web.php',
            __DIR__.'/Fixture/violation_api.php',
        ], [
            [sprintf(self::MSG, 'MisplacedApiController', '/api/foo', '"api_foo"', 'non-API'), 9],
            [sprintf(self::MSG, 'WebNamedApiController', '/dashboard', '"api_dashboard"', 'non-API'), 14],
            [sprintf(self::MSG, 'ApiNamespacedWebController', '/pages', '"page_list"', 'Controller\\Api'), 9],
        ]);
    }
}
