<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\ControllerNoDirectStateAccessRule;

use Gamache\PHPStan\ControllerNoDirectStateAccessRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ControllerNoDirectStateAccessRule>
 */
final class ControllerNoDirectStateAccessRuleTest extends RuleTestCase
{
    private const string CONTROLLER_BASE = 'App\Controller\AppController';

    protected function getRule(): Rule
    {
        return new ControllerNoDirectStateAccessRule($this->createReflectionProvider(), self::CONTROLLER_BASE);
    }

    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/config.neon'];
    }

    public function test_delegating_controller_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_direct_persistence_access_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Controller WritingController must not access persistent state directly (findAll()); read and write through a Command/Handler.',
                22,
            ],
            [
                'Controller WritingController must not access persistent state directly (find()); read and write through a Command/Handler.',
                23,
            ],
            [
                'Controller WritingController must not access persistent state directly (persist()); read and write through a Command/Handler.',
                24,
            ],
            [
                'Controller WritingController must not access persistent state directly (flush()); read and write through a Command/Handler.',
                25,
            ],
        ]);
    }
}
