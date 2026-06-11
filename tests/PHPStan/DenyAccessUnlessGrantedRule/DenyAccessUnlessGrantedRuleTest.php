<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\DenyAccessUnlessGrantedRule;

use Gamache\PHPStan\DenyAccessUnlessGrantedRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<DenyAccessUnlessGrantedRule>
 */
final class DenyAccessUnlessGrantedRuleTest extends RuleTestCase
{
    private const string CONTROLLER_BASE = 'App\Controller\AppController';

    protected function getRule(): Rule
    {
        return new DenyAccessUnlessGrantedRule($this->createReflectionProvider(), self::CONTROLLER_BASE);
    }

    public function test_correct_usage_passes(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/valid.php',
        ], []);
    }

    public function test_exempted_controller_passes(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/exempted.php',
        ], []);
    }

    public function test_deny_access_in_invoke_is_reported(): void
    {
        $msg = 'AppController::__invoke() must not call $this->denyAccessUnlessGranted(). '
              .'Use #[IsGranted] with a Voter constant and subject. '
              .'To exempt dynamic-subject controllers, add "access is enforced per-branch" to the class docblock.';

        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/violation.php',
        ], [
            [$msg, 12],
            [$msg, 23],
        ]);
    }
}
