<?php

declare(strict_types=1);

namespace App\Module\Review\Mcp;

use Gamache\Tests\PHPStan\Fixtures\Mcp\ReviseDocumentHandler;
use Gamache\Tests\PHPStan\Fixtures\Mcp\ShowReviewHandler;
use Mcp\Capability\Attribute\McpTool;

// The handler already declares this shape; the copy can only go stale.
#[McpTool(name: 'document_get_review')]
final readonly class DocumentGetReviewTool
{
    public function __construct(private ShowReviewHandler $showReview)
    {
    }

    /** @return array{status: string, verdict: string|null, version: int} */
    public function __invoke(string $documentId): array
    {
        return ($this->showReview)($documentId);
    }
}

// Same duplication, delegated through a named method.
#[McpTool(name: 'document_revise')]
final readonly class DocumentReviseTool
{
    public function __construct(private ReviseDocumentHandler $handler)
    {
    }

    /** @return array{carried: int, orphaned: int} */
    public function __invoke(string $documentId): array
    {
        return $this->handler->handle($documentId);
    }
}

// The real shape: authorization, a size guard, and error translation around a
// single delegating return.
#[McpTool(name: 'document_get_review_guarded')]
final readonly class GuardedReviewTool
{
    public function __construct(private ShowReviewHandler $showReview)
    {
    }

    /** @return array{status: string, verdict: string|null, version: int} */
    public function __invoke(string $documentId): array
    {
        try {
            if ('' === $documentId) {
                throw new \InvalidArgumentException('A document id is required.');
            }

            return ($this->showReview)($documentId);
        } catch (\Throwable $e) {
            throw new \RuntimeException('The review could not be read.', 0, $e);
        }
    }
}
