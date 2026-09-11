<?php

declare(strict_types=1);

namespace App\Module\Review\Mcp\Direct;

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
