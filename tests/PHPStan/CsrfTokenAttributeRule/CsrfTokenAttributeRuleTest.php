<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\CsrfTokenAttributeRule;

use Gamache\PHPStan\CsrfTokenAttributeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<CsrfTokenAttributeRule>
 */
final class CsrfTokenAttributeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new CsrfTokenAttributeRule('App\Controller\AppController', 'App\Security\Attribute\CsrfToken');
    }

    public function test_controller_without_imperative_checks_passes(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/valid.php',
        ], []);
    }

    public function test_imperative_checks_in_controller_are_reported(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/violation.php',
        ], [
            [
                'Controller must not call isCsrfTokenValid() to validate CSRF tokens imperatively. Use the #[App\Security\Attribute\CsrfToken] attribute instead; validation runs in the listener before the action.',
                16,
            ],
            [
                'Controller must not call isTokenValid() to validate CSRF tokens imperatively. Use the #[App\Security\Attribute\CsrfToken] attribute instead; validation runs in the listener before the action.',
                17,
            ],
        ]);
    }

    public function test_non_controller_is_exempt(): void
    {
        $this->analyse([__DIR__.'/Fixture/non_controller.php'], []);
    }
}
