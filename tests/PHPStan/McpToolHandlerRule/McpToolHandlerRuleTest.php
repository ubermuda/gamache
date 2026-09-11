<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\McpToolHandlerRule;

use Gamache\PHPStan\McpToolHandlerRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<McpToolHandlerRule>
 */
final class McpToolHandlerRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new McpToolHandlerRule($this->createReflectionProvider());
    }

    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/config.neon'];
    }

    public function test_a_tool_injecting_a_handler_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_a_tool_with_no_handler_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            ['MCP tool TagListTool injects no handler; give it a Command/Handler pair to delegate to.', 33],
            ['MCP tool CardGetTool injects no handler; give it a Command/Handler pair to delegate to.', 49],
            ['MCP tool SeriesListTool injects no handler; give it a Command/Handler pair to delegate to.', 65],
            ['MCP tool DocumentGetTool injects no handler; give it a Command/Handler pair to delegate to.', 76],
            ['MCP tool DocumentTouchTool injects no handler; give it a Command/Handler pair to delegate to.', 91],
            ['MCP tool DocumentPresentTool injects no handler; give it a Command/Handler pair to delegate to.', 105],
            ['MCP tool DocumentOverrideTool injects no handler; give it a Command/Handler pair to delegate to.', 116],
            ['MCP tool DocumentDiscardTool injects no handler; give it a Command/Handler pair to delegate to.', 130],
        ]);
    }
}
