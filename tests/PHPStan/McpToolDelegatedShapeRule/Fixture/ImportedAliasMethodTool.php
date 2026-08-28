<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\McpToolDelegatedShapeRule\Fixture;

use Gamache\Tests\PHPStan\Fixtures\Mcp\ReviseDocumentHandler;
use Mcp\Capability\Attribute\McpTool;

/**
 * @phpstan-import-type RevisionPayload from ReviseDocumentHandler
 */
#[McpTool(name: 'document_revise')]
final readonly class ImportedAliasMethodTool
{
    public function __construct(private ReviseDocumentHandler $handler)
    {
    }

    /** @return RevisionPayload */
    public function __invoke(string $documentId): array
    {
        return $this->handler->handle($documentId);
    }
}
