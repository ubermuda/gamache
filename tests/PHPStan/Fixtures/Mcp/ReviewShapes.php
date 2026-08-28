<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/**
 * Declares the same shape as ShowReviewHandler, independently of it.
 *
 * @phpstan-type ReviewPayload array{status: string, verdict: string|null, version: int}
 */
final readonly class ReviewShapes
{
}
