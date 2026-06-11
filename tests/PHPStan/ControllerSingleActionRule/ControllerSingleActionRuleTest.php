<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\ControllerSingleActionRule;

use Gamache\PHPStan\ControllerSingleActionRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ControllerSingleActionRule>
 */
final class ControllerSingleActionRuleTest extends RuleTestCase
{
    private const string CONTROLLER_BASE = 'App\Controller\AppController';

    protected function getRule(): Rule
    {
        return new ControllerSingleActionRule($this->createReflectionProvider(), self::CONTROLLER_BASE);
    }

    public function test_single_invoke_passes(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/valid.php',
        ], []);
    }

    public function test_extra_public_method_is_reported(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/violation.php',
        ], [
            [
                'Controller MultiActionController must have exactly one public method: __invoke().',
                8,
            ],
        ]);
    }
}
