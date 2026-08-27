<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/** Delegated to through a named method rather than __invoke. */
final readonly class ReviseDocumentHandler
{
    /** @return array{carried: int, orphaned: int} */
    public function handle(string $documentId): array
    {
        return ['carried' => 0, 'orphaned' => 0];
    }
}
