<?php

declare(strict_types=1);

namespace App\Module\Review\Mcp\Direct;

use Gamache\Tests\PHPStan\Fixtures\Mcp\ArchiveDocumentHandler;
use Gamache\Tests\PHPStan\Fixtures\Mcp\DocumentPresenter as DocumentHandler;
use Gamache\Tests\PHPStan\Fixtures\Mcp\DiscardingBaseTool;
use Gamache\Tests\PHPStan\Fixtures\Mcp\HandlerAwareTool;
use Gamache\Tests\PHPStan\Fixtures\Mcp\PresenterAwareTool;
use Mcp\Capability\Attribute\McpTool;

final readonly class TagRepository
{
    /** @return list<string> */
    public function findAll(): array
    {
        return [];
    }
}

final readonly class CardPayload
{
    /** @return array{id: string} */
    public function forCard(string $cardId): array
    {
        return ['id' => $cardId];
    }
}

// Reads a repository with nothing to delegate to.
#[McpTool(name: 'tag_list')]
final readonly class TagListTool
{
    public function __construct(
        private TagRepository $tags,
    ) {
    }

    /** @return list<string> */
    public function __invoke(): array
    {
        return $this->tags->findAll();
    }
}

// Collaborators, but none of them a handler.
#[McpTool(name: 'card_get')]
final readonly class CardGetTool
{
    public function __construct(
        private CardPayload $payload,
    ) {
    }

    /** @return array{id: string} */
    public function __invoke(string $cardId): array
    {
        return $this->payload->forCard($cardId);
    }
}

// No constructor at all.
#[McpTool(name: 'series_list')]
final readonly class SeriesListTool
{
    /** @return list<string> */
    public function __invoke(): array
    {
        return [];
    }
}

// Aliased to a name that says Handler. The class it names does not.
#[McpTool(name: 'document_get')]
final readonly class DocumentGetTool
{
    public function __construct(
        private DocumentHandler $presenter,
    ) {
    }

    public function __invoke(string $documentId): string
    {
        return $this->presenter->present($documentId);
    }
}

// Takes a handler and drops it. Nothing is left for __invoke() to call.
#[McpTool(name: 'document_touch')]
final class DocumentTouchTool
{
    public function __construct(ArchiveDocumentHandler $archive)
    {
    }

    public function __invoke(string $documentId): string
    {
        return $documentId;
    }
}

// A parent, but not one that injects a handler.
#[McpTool(name: 'document_present')]
final class DocumentPresentTool extends PresenterAwareTool
{
    public function __invoke(string $documentId): string
    {
        return $this->presenter->present($documentId);
    }
}

// Overrides the parent's constructor without running it, so the parent's
// handler property is never set.
#[McpTool(name: 'document_override')]
final class DocumentOverrideTool extends HandlerAwareTool
{
    public function __construct()
    {
    }

    public function __invoke(string $documentId): string
    {
        return $documentId;
    }
}

// The parent takes a handler and drops it, so there is nothing to inherit.
#[McpTool(name: 'document_discard')]
final class DocumentDiscardTool extends DiscardingBaseTool
{
    public function __invoke(string $documentId): string
    {
        return $documentId;
    }
}
