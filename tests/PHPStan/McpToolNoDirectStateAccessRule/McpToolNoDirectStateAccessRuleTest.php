<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\McpToolNoDirectStateAccessRule;

use Gamache\PHPStan\McpToolNoDirectStateAccessRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<McpToolNoDirectStateAccessRule>
 */
final class McpToolNoDirectStateAccessRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new McpToolNoDirectStateAccessRule($this->createReflectionProvider());
    }

    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/config.neon'];
    }

    public function test_a_delegating_tool_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_direct_persistence_access_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            ['MCP tool CardListTool must not access persistent state directly (findAll()); read and write through a Command/Handler.', 24],
            ['MCP tool SeriesRenameTool must not access persistent state directly (flush()); read and write through a Command/Handler.', 45],
            ['MCP tool SeriesRenameTool must not access persistent state directly (findAll()); read and write through a Command/Handler.', 47],
        ]);
    }
}
