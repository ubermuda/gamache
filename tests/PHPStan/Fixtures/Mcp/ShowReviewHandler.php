<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/**
 * A query handler that declares its own result shape, so a tool delegating to
 * it has a shape available to restate — or to import.
 *
 * @phpstan-type ReviewPayload array{status: string, verdict: string|null, version: int}
 */
final readonly class ShowReviewHandler
{
    /** @return ReviewPayload */
    public function __invoke(string $documentId): array
    {
        return ['status' => 'open', 'verdict' => null, 'version' => 1];
    }
}
