<?php

declare(strict_types=1);

namespace App\Module\Review\Mcp;

use Mcp\Capability\Attribute\McpTool;

// The class was renamed and the tool name left behind.
#[McpTool(name: 'document_update')]
final readonly class DocumentReviseTool
{
}

// camelCase where the convention is snake_case.
#[McpTool(name: 'documentGetReview')]
final readonly class DocumentGetReviewTool
{
}

// Positional argument, same mismatch.
#[McpTool('review_get')]
final readonly class SiteReviewGetTool
{
}

// The constant is read, so a mismatch behind one is reported too.
#[McpTool(name: self::NAME)]
final readonly class DocumentHighlightTool
{
    public const string NAME = 'document_highlights';
}
