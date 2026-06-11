<?php

declare(strict_types=1);

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;

class MissingRouteController extends AppController
{
    public function __invoke(): Response
    {
        return new Response();
    }
}
