<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\McpToolDelegatedShapeRule\Fixture;

use Gamache\Tests\PHPStan\Fixtures\Mcp\ReviewShapes;
use Gamache\Tests\PHPStan\Fixtures\Mcp\ShowReviewHandler;
use Mcp\Capability\Attribute\McpTool;

/**
 * @phpstan-import-type ReviewPayload from ReviewShapes
 */
#[McpTool(name: 'document_get_review')]
final readonly class ForeignAliasTool
{
    public function __construct(private ShowReviewHandler $showReview)
    {
    }

    /** @return ReviewPayload */
    public function __invoke(string $documentId): array
    {
        return ($this->showReview)($documentId);
    }
}
