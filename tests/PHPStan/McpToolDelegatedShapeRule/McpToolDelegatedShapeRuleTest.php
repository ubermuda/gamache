<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\McpToolDelegatedShapeRule;

use Gamache\PHPStan\McpToolDelegatedShapeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<McpToolDelegatedShapeRule>
 */
final class McpToolDelegatedShapeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new McpToolDelegatedShapeRule();
    }

    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/config.neon'];
    }

    public function test_a_tool_that_does_not_copy_its_delegate_shape_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_a_restated_delegate_shape_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'MCP tool App\Module\Review\Mcp\DocumentGetReviewTool::__invoke() restates the array shape $this->showReview already declares. Drop the duplicate so the two cannot drift.',
                20,
            ],
            [
                'MCP tool App\Module\Review\Mcp\DocumentReviseTool::__invoke() restates the array shape $this->handler->handle() already declares. Drop the duplicate so the two cannot drift.',
                35,
            ],
            [
                'MCP tool App\Module\Review\Mcp\GuardedReviewTool::__invoke() restates the array shape $this->showReview already declares. Drop the duplicate so the two cannot drift.',
                51,
            ],
        ]);
    }
}
