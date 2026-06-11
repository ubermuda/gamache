<?php

declare(strict_types=1);

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/valid', name: 'test_valid')]
class RoutedController extends AppController
{
    public function __invoke(): Response
    {
        return new Response();
    }
}
