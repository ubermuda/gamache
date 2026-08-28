<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/**
 * Delegated to through a named method rather than __invoke.
 *
 * @phpstan-type RevisionPayload array{carried: int, orphaned: int}
 */
final readonly class ReviseDocumentHandler
{
    /** @return RevisionPayload */
    public function handle(string $documentId): array
    {
        return ['carried' => 0, 'orphaned' => 0];
    }
}
