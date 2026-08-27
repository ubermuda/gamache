<?php

declare(strict_types=1);

namespace App\Module\Review\Mcp;

use Mcp\Capability\Attribute\McpTool;

#[McpTool(name: 'document_revise', description: 'Submit a new version.')]
final readonly class DocumentReviseTool
{
}

#[McpTool(name: 'tag_list')]
final readonly class TagListTool
{
}

#[McpTool(name: 'site_review_mark_comment_addressed')]
final readonly class SiteReviewMarkCommentAddressedTool
{
}

// Positional name argument.
#[McpTool('document_get')]
final readonly class DocumentGetTool
{
}

// Not a tool: the attribute is what scopes the rule.
final readonly class DocumentPurgeTool
{
}

// A tool publishing its own name as a constant is read through it.
#[McpTool(name: self::NAME)]
final readonly class DocumentHighlightTool
{
    public const string NAME = 'document_highlight';
}

// A name built any other way cannot be compared.
#[McpTool(name: ToolNames::DOCUMENT_PURGE)]
final readonly class DocumentPurgeElsewhereTool
{
}
