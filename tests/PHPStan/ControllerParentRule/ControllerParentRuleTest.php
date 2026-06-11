<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\ControllerParentRule;

use Gamache\PHPStan\ControllerParentRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ControllerParentRule>
 */
final class ControllerParentRuleTest extends RuleTestCase
{
    private const string CONTROLLER_BASE = 'App\Controller\AppController';

    protected function getRule(): Rule
    {
        return new ControllerParentRule($this->createReflectionProvider(), self::CONTROLLER_BASE);
    }

    public function test_valid_controller_passes(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/valid.php',
        ], []);
    }

    public function test_wrong_parent_is_reported(): void
    {
        $this->analyse([
            __DIR__.'/../Fixtures/AppController.php',
            __DIR__.'/Fixture/violation.php',
        ], [
            [
                'Controller class WrongParentController must extend AppController.',
                8,
            ],
        ]);
    }
}
