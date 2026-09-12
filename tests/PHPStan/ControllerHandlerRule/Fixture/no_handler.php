<?php

declare(strict_types=1);

namespace App\Module\Review\Controller\Direct;

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;

final class ArchiveReviewController extends AppController
{
    public function __construct(
        private readonly string $uploadDirectory,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('review/archive.html.twig', ['directory' => $this->uploadDirectory]);
    }
}
