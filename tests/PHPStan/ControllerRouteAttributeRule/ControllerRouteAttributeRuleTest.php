<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\ControllerRouteAttributeRule;

use Gamache\PHPStan\ControllerRouteAttributeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ControllerRouteAttributeRule>
 */
final class ControllerRouteAttributeRuleTest extends RuleTestCase
{
    private const string CONTROLLER_BASE = 'App\Controller\AppController';

    protected function getRule(): Rule
    {
        return new ControllerRouteAttributeRule($this->createReflectionProvider(), self::CONTROLLER_BASE);
    }

    public function test_route_attribute_passes(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/valid.php',
        ], []);
    }

    public function test_missing_route_is_reported(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/violation.php',
        ], [
            [
                'Controller MissingRouteController must have a #[Route] attribute.',
                8,
            ],
        ]);
    }
}
