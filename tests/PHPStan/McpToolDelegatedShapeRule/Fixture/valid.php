<?php

declare(strict_types=1);

namespace App\Module\Review\Mcp\Valid;

use Gamache\Tests\PHPStan\Fixtures\Mcp\ShowReviewHandler;
use Gamache\Tests\PHPStan\Fixtures\Mcp\TagRepository;
use Mcp\Capability\Attribute\McpTool;

// Wraps the delegated shape rather than restating it: its own shape.
#[McpTool(name: 'document_get_review')]
final readonly class WrappingReviewTool
{
    public function __construct(private ShowReviewHandler $showReview)
    {
    }

    /** @return array{review: array{status: string, verdict: string|null, version: int}} */
    public function __invoke(string $documentId): array
    {
        return ['review' => ($this->showReview)($documentId)];
    }
}

// Assembles its own array: the same text would be its own declaration.
#[McpTool(name: 'tag_list')]
final readonly class TagListTool
{
    public function __construct(private TagRepository $tags)
    {
    }

    /** @return array{tags: list<array{name: string, documentCount: int}>} */
    public function __invoke(): array
    {
        return ['tags' => $this->tags->findAll()];
    }
}

// Narrows what the handler declares, which is a decision rather than a copy.
#[McpTool(name: 'document_status')]
final readonly class DocumentStatusTool
{
    public function __construct(private ShowReviewHandler $showReview)
    {
    }

    /** @return array{status: string} */
    public function __invoke(string $documentId): array
    {
        $review = ($this->showReview)($documentId);

        return ['status' => $review['status']];
    }
}

// No @return of its own to go stale.
#[McpTool(name: 'document_untyped')]
final readonly class UntypedTool
{
    public function __construct(private ShowReviewHandler $showReview)
    {
    }

    public function __invoke(string $documentId): array
    {
        return ($this->showReview)($documentId);
    }
}

// Not a tool: the attribute is what scopes the rule.
final readonly class ShowReviewFacade
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
