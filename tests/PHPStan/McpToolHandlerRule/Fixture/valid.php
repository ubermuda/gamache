<?php

declare(strict_types=1);

namespace App\Module\Review\Mcp\Delegating;

use Gamache\Tests\PHPStan\Fixtures\Mcp\ArchiveDocumentHandler as Archiver;
use Gamache\Tests\PHPStan\Fixtures\Mcp\HandlerAwareTool;
use Mcp\Capability\Attribute\McpTool;

final readonly class ReviseDocumentHandler
{
    public function __invoke(string $documentId): void
    {
    }
}

final readonly class DocumentRepository
{
    public function countBySeries(string $series): int
    {
        return 0;
    }
}

// Injects a handler and delegates to it.
#[McpTool(name: 'document_revise')]
final readonly class DocumentReviseTool
{
    public function __construct(
        private ReviseDocumentHandler $handler,
    ) {
    }

    public function __invoke(string $documentId): void
    {
        ($this->handler)($documentId);
    }
}

// Injects a handler and also queries a repository. This rule asks only whether
// a handler is there; the repository is McpToolNoDirectStateAccessRule's find.
#[McpTool(name: 'series_rename')]
final readonly class SeriesRenameTool
{
    public function __construct(
        private ReviseDocumentHandler $renameSeries,
        private DocumentRepository $documents,
    ) {
    }

    public function __invoke(string $series): int
    {
        ($this->renameSeries)($series);

        return $this->documents->countBySeries($series);
    }
}

// Not promoted: a constructor that assigns by hand injects it just the same.
#[McpTool(name: 'document_archive')]
final class DocumentArchiveTool
{
    private ReviseDocumentHandler $handler;

    public function __construct(ReviseDocumentHandler $handler)
    {
        $this->handler = $handler;
    }

    public function __invoke(string $documentId): void
    {
        ($this->handler)($documentId);
    }
}

// Not a tool: the attribute is what scopes the rule.
final readonly class DocumentPurgeTool
{
    public function __construct(
        private DocumentRepository $documents,
    ) {
    }

    public function __invoke(string $series): int
    {
        return $this->documents->countBySeries($series);
    }
}

// Imported under an alias that does not say Handler. The class it names does.
#[McpTool(name: 'document_unarchive')]
final readonly class DocumentUnarchiveTool
{
    public function __construct(
        private Archiver $archive,
    ) {
    }

    public function __invoke(string $documentId): void
    {
        ($this->archive)($documentId);
    }
}

// The parent's constructor injects the handler, so the subclass delegates too.
#[McpTool(name: 'document_archive_again')]
final class InheritingTool extends HandlerAwareTool
{
    public function __invoke(string $documentId): void
    {
        ($this->archive)($documentId);
    }
}
