<?php

declare(strict_types=1);

namespace App\Module\Review\Controller\Rendering;

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;

final class ShowLegalController extends AppController
{
    public function __invoke(): Response
    {
        return $this->render('review/legal.html.twig');
    }
}
