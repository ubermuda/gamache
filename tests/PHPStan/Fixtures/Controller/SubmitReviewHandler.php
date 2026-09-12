<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Controller;

/**
 * A handler a controller-rule fixture delegates to.
 */
final readonly class SubmitReviewHandler
{
    public function __invoke(string $reviewId): void
    {
    }
}
