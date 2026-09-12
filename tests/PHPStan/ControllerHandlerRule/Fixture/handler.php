<?php

declare(strict_types=1);

namespace App\Module\Review\Controller\Delegating;

use App\Controller\AppController;
use Gamache\Tests\PHPStan\Fixtures\Controller\SubmitReviewHandler;
use Symfony\Component\HttpFoundation\Response;

final class SubmitReviewController extends AppController
{
    public function __construct(
        private readonly SubmitReviewHandler $submitReview,
    ) {
    }

    public function __invoke(string $reviewId): Response
    {
        ($this->submitReview)($reviewId);

        return $this->redirectToRoute('review_show', ['id' => $reviewId]);
    }
}
