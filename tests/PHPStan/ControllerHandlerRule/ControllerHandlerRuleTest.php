<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\ControllerHandlerRule;

use Gamache\PHPStan\ControllerHandlerRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ControllerHandlerRule>
 */
final class ControllerHandlerRuleTest extends RuleTestCase
{
    private const string CONTROLLER_BASE = 'App\Controller\AppController';

    /** @var list<string> */
    private array $ignoredControllers = [];

    protected function getRule(): Rule
    {
        return new ControllerHandlerRule(
            $this->createReflectionProvider(),
            self::CONTROLLER_BASE,
            $this->ignoredControllers,
        );
    }

    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/config.neon'];
    }

    public function test_a_controller_injecting_a_handler_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/handler.php'], []);
    }

    public function test_a_controller_with_a_constructor_and_no_handler_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/no_handler.php'], [
            ['Controller ArchiveReviewController injects no handler; give it a Command/Handler pair to delegate to.', 10],
        ]);
    }

    public function test_a_controller_injecting_only_other_collaborators_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/collaborators_only.php'], [
            ['Controller ShowProfileController injects no handler; give it a Command/Handler pair to delegate to.', 12],
        ]);
    }

    public function test_a_controller_with_no_constructor_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/no_constructor.php'], []);
    }

    public function test_a_class_outside_the_controller_base_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/not_a_controller.php'], []);
    }

    public function test_an_unlisted_controller_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/ignored.php'], [
            ['Controller LoginController injects no handler; give it a Command/Handler pair to delegate to.', 16],
        ]);
    }

    public function test_a_listed_controller_passes(): void
    {
        $this->ignoredControllers = ['App\Module\Account\Controller\LoginController'];

        $this->analyse([__DIR__.'/Fixture/ignored.php'], []);
    }
}
