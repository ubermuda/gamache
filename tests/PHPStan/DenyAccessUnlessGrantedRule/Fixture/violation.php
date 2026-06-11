<?php

declare(strict_types=1);

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;

class ViolatingController extends AppController
{
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return new Response('ok');
    }
}

class NestedViolatingController extends AppController
{
    public function __invoke(bool $flag): Response
    {
        if ($flag) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

        return new Response('ok');
    }
}
