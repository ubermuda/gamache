<?php

declare(strict_types=1);

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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

class HttpExceptionController extends AppController
{
    public function __invoke(bool $flag): Response
    {
        if ($flag) {
            throw new AccessDeniedHttpException('nope');
        }

        return new Response('ok');
    }
}

class SecurityExceptionController extends AppController
{
    public function __invoke(): Response
    {
        throw new AccessDeniedException('nope');
    }
}

class CreateExceptionController extends AppController
{
    public function __invoke(): Response
    {
        throw $this->createAccessDeniedException('nope');
    }
}
