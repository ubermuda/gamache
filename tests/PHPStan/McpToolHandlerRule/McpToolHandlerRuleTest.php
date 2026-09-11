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
        return new McpToolHandlerRule();
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
            ['MCP tool TagListTool injects no handler; give it a Command/Handler pair to delegate to.', 30],
            ['MCP tool CardGetTool injects no handler; give it a Command/Handler pair to delegate to.', 46],
            ['MCP tool SeriesListTool injects no handler; give it a Command/Handler pair to delegate to.', 62],
            ['MCP tool DocumentGetTool injects no handler; give it a Command/Handler pair to delegate to.', 73],
            ['MCP tool DocumentTouchTool injects no handler; give it a Command/Handler pair to delegate to.', 88],
        ]);
    }
}
