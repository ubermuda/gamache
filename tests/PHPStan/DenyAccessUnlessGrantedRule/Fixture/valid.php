<?php

declare(strict_types=1);

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

// Uses #[IsGranted] — no denyAccessUnlessGranted call
class ValidController extends AppController
{
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}

// Non-AppController class — may call it freely
class NotAnAppController
{
    public function __invoke(): void
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        throw new AccessDeniedHttpException('nope');
    }
}

// Non-__invoke method — may call it freely
class AnotherController extends AppController
{
    public function helper(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        throw new AccessDeniedException('nope');
    }
}
