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

    public function test_docblock_phrase_does_not_exempt(): void
    {
        // The old "access is enforced per-branch" docblock escape hatch was
        // removed: a class comment no longer suppresses the rule.
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/docblock_phrase.php',
        ], [
            [self::message('call $this->denyAccessUnlessGranted()'), 16],
            [self::message('instantiate AccessDeniedHttpException'), 19],
        ]);
    }

    public function test_deny_access_in_invoke_is_reported(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/violation.php',
        ], [
            [self::message('call $this->denyAccessUnlessGranted()'), 14],
            [self::message('call $this->denyAccessUnlessGranted()'), 25],
            [self::message('instantiate AccessDeniedHttpException'), 37],
            [self::message('instantiate AccessDeniedException'), 48],
            [self::message('call $this->createAccessDeniedException()'), 56],
        ]);
    }

    private static function message(string $construct): string
    {
        return \sprintf('AppController::__invoke() must not %s. ', $construct)
            .'Use #[IsGranted] with a Voter constant and subject. '
            .'If the subject is only resolvable at runtime (e.g. from a query parameter), call denyAccessUnlessGranted() from a private helper method, not __invoke().';
    }
}
