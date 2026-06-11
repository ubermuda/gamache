<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\RouteNoUnderscorePrefixRule;

use Gamache\PHPStan\RouteNoUnderscorePrefixRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<RouteNoUnderscorePrefixRule>
 */
final class RouteNoUnderscorePrefixRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RouteNoUnderscorePrefixRule();
    }

    public function test_normal_route_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_underscore_prefixed_route_is_reported(): void
    {
        $msg = 'Route path "%s" must not begin with "/_". That prefix is reserved for Symfony internals.';

        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [sprintf($msg, '/_workspace'), 7],
            [sprintf($msg, '/_admin/users'), 12],
        ]);
    }
}
