<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\McpToolNameRule;

use Gamache\PHPStan\McpToolNameRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<McpToolNameRule>
 */
final class McpToolNameRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new McpToolNameRule();
    }

    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/config.neon'];
    }

    public function test_a_tool_name_matching_its_class_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_a_mismatched_tool_name_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'MCP tool DocumentReviseTool declares the tool name "document_update"; expected "document_revise". Rename the tool, or rename the class to DocumentUpdateTool.',
                10,
            ],
            [
                'MCP tool DocumentGetReviewTool declares the tool name "documentGetReview"; expected "document_get_review".',
                16,
            ],
            [
                'MCP tool SiteReviewGetTool declares the tool name "review_get"; expected "site_review_get". Rename the tool, or rename the class to ReviewGetTool.',
                22,
            ],
            [
                'MCP tool DocumentHighlightTool declares the tool name "document_highlights"; expected "document_highlight". Rename the tool, or rename the class to DocumentHighlightsTool.',
                28,
            ],
        ]);
    }
}
