<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/**
 * A query handler that declares its own result shape, so a tool delegating to
 * it has a shape available to restate.
 */
final readonly class ShowReviewHandler
{
    /** @return array{status: string, verdict: string|null, version: int} */
    public function __invoke(string $documentId): array
    {
        return ['status' => 'open', 'verdict' => null, 'version' => 1];
    }
}
