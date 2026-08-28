<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\McpToolDelegatedShapeRule\Fixture;

use Gamache\Tests\PHPStan\Fixtures\Mcp\ShowReviewHandler;
use Mcp\Capability\Attribute\McpTool;

/**
 * @phpstan-type ReviewPayload array{status: string, verdict: string|null, version: int}
 */
#[McpTool(name: 'document_get_review')]
final readonly class LocalAliasTool
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
