<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\MessengerHandlerNamespaceRule;

use Gamache\PHPStan\MessengerHandlerNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<MessengerHandlerNamespaceRule>
 */
final class MessengerHandlerNamespaceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new MessengerHandlerNamespaceRule();
    }

    public function test_handler_sharing_namespace_with_message_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_handler_in_different_namespace_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Message handler App\Module\Project\Messenger\StrayMessageHandler and its message App\Module\Other\StrayMessage must live in the same namespace.',
                10,
            ],
            [
                'Message handler App\Module\Project\Messenger\ExplicitHandlesHandler and its message App\Module\Other\StrayMessage must live in the same namespace.',
                18,
            ],
            [
                'Message handler App\Module\Project\Messenger\MethodLevelHandler and its message App\Module\Other\StrayMessage must live in the same namespace.',
                28,
            ],
            [
                'Message handler App\Module\Project\Messenger\CustomMethodHandler and its message App\Module\Other\StrayMessage must live in the same namespace.',
                34,
            ],
        ]);
    }
}
