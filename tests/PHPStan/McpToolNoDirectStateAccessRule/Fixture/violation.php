<?php

declare(strict_types=1);

namespace App\Module\Board\Mcp\Leaky;

use Doctrine\ORM\EntityManagerInterface;
use Gamache\Tests\PHPStan\Fixtures\Mcp\CardRepository;
use Gamache\Tests\PHPStan\Fixtures\Mcp\RenameSeriesHandler;
use Mcp\Capability\Attribute\McpTool;

// A repository query with nothing to delegate to.
#[McpTool(name: 'card_list')]
final readonly class CardListTool
{
    public function __construct(
        private CardRepository $cards,
    ) {
    }

    /** @return list<object> */
    public function __invoke(): array
    {
        return $this->cards->findAll();
    }
}

// Delegates the write and still reads for the response. Injecting a handler is
// not a defence: this is the shape the rule exists for.
#[McpTool(name: 'series_rename')]
final readonly class SeriesRenameTool
{
    public function __construct(
        private RenameSeriesHandler $renameSeries,
        private CardRepository $cards,
        private EntityManagerInterface $em,
    ) {
    }

    /** @return array{series: string, count: int} */
    public function __invoke(string $series): array
    {
        $renamed = ($this->renameSeries)($series);

        $this->em->flush();

        return ['series' => $renamed, 'count' => \count($this->cards->findAll())];
    }
}
