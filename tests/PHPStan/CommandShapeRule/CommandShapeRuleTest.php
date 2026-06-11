<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\CommandShapeRule;

use Gamache\PHPStan\CommandShapeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<CommandShapeRule>
 */
final class CommandShapeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new CommandShapeRule();
    }

    public function test_valid_command_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_invalid_command_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Command BadCommand must be declared as "final readonly class".',
                7,
            ],
            [
                'Command BadCommand must not declare public methods other than __construct().',
                7,
            ],
        ]);
    }
}
