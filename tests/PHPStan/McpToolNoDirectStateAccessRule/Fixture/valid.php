<?php

declare(strict_types=1);

namespace App\Module\Board\Mcp\Clean;

use Gamache\Tests\PHPStan\Fixtures\Mcp\BoardSubjectResolver;
use Gamache\Tests\PHPStan\Fixtures\Mcp\CardPayload;
use Gamache\Tests\PHPStan\Fixtures\Mcp\CardRepository;
use Gamache\Tests\PHPStan\Fixtures\Mcp\RenameSeriesHandler;
use Mcp\Capability\Attribute\McpTool;

// Delegates through a callable, which is a FuncCall rather than a MethodCall.
#[McpTool(name: 'series_rename')]
final readonly class SeriesRenameTool
{
    public function __construct(
        private RenameSeriesHandler $renameSeries,
    ) {
    }

    public function __invoke(string $series): string
    {
        return ($this->renameSeries)($series);
    }
}

// Calls collaborators that are not persistence types.
#[McpTool(name: 'card_get')]
final readonly class CardGetTool
{
    public function __construct(
        private BoardSubjectResolver $subjects,
        private CardPayload $payload,
    ) {
    }

    /** @return array{id: string} */
    public function __invoke(string $cardId): array
    {
        return $this->payload->forCard($this->subjects->requireCard($cardId));
    }
}

// An inherited helper is called on $this, not on a persistence collaborator.
#[McpTool(name: 'card_describe')]
final class CardDescribeTool extends CardToolBase
{
    public function __invoke(string $cardId): string
    {
        return $this->describe($cardId);
    }
}

abstract class CardToolBase
{
    protected function describe(string $cardId): string
    {
        return $cardId;
    }
}

// Not a tool: the attribute is what scopes the rule.
final readonly class CardPurgeTool
{
    public function __construct(
        private CardRepository $cards,
    ) {
    }

    public function __invoke(string $cardId): ?object
    {
        return $this->cards->find($cardId);
    }
}
