<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\IsGrantedClassLevelRule;

use Gamache\PHPStan\IsGrantedClassLevelRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<IsGrantedClassLevelRule>
 */
final class IsGrantedClassLevelRuleTest extends RuleTestCase
{
    private const string CONTROLLER_BASE = 'App\Controller\AppController';

    protected function getRule(): Rule
    {
        return new IsGrantedClassLevelRule($this->createReflectionProvider(), self::CONTROLLER_BASE);
    }

    public function test_class_level_is_granted_passes(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/valid.php',
        ], []);
    }

    public function test_method_level_is_granted_is_reported(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/violation.php',
        ], [
            [
                '#[IsGranted] on MethodLevelIsGrantedController::__invoke() must be declared at the class level, not on the method (single-action controllers carry access control on the class, like #[Route]). The subject still resolves from the controller arguments.',
                11,
            ],
        ]);
    }
}
