<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\McpToolDelegatedShapeRule;

use Gamache\PHPStan\McpToolDelegatedShapeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * PHPStan resolves a type alias only for a class its reflection provider can
 * find, so the alias fixtures are one autoloadable class per file rather than
 * the several-classes-per-file style of the rest.
 *
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

    public function test_a_return_naming_an_alias_imported_from_the_delegate_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/ImportedAliasTool.php'], []);
    }

    public function test_a_return_naming_an_alias_imported_from_a_delegate_method_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/ImportedAliasMethodTool.php'], []);
    }

    public function test_a_restated_delegate_shape_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'MCP tool App\Module\Review\Mcp\DocumentGetReviewTool::__invoke() restates the array shape $this->showReview already declares. Import that shape with @phpstan-import-type instead of restating it.',
                20,
            ],
            [
                'MCP tool App\Module\Review\Mcp\DocumentReviseTool::__invoke() restates the array shape $this->handler->handle() already declares. Import that shape with @phpstan-import-type instead of restating it.',
                35,
            ],
            [
                'MCP tool App\Module\Review\Mcp\GuardedReviewTool::__invoke() restates the array shape $this->showReview already declares. Import that shape with @phpstan-import-type instead of restating it.',
                51,
            ],
        ]);
    }

    public function test_an_alias_the_tool_defines_itself_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/LocalAliasTool.php'], [
            [
                'MCP tool Gamache\Tests\PHPStan\McpToolDelegatedShapeRule\Fixture\LocalAliasTool::__invoke() restates the array shape $this->showReview already declares. Import that shape with @phpstan-import-type instead of restating it.',
                21,
            ],
        ]);
    }

    public function test_an_alias_imported_from_a_class_other_than_the_delegate_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/ForeignAliasTool.php'], [
            [
                'MCP tool Gamache\Tests\PHPStan\McpToolDelegatedShapeRule\Fixture\ForeignAliasTool::__invoke() restates the array shape $this->showReview already declares. Import that shape with @phpstan-import-type instead of restating it.',
                22,
            ],
        ]);
    }
}
