<?php

declare(strict_types=1);

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Request;
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

// Role-only check — no subject to express as #[IsGranted], rule does not apply
class RoleOnlyDenyController extends AppController
{
    public function __invoke(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return new Response('ok');
    }
}

// Subject only resolvable at runtime (no route parameter) — rule does not apply
class RuntimeSubjectController extends AppController
{
    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('WORKSPACE_VIEW', $request->query->get('topic'));

        return new Response('ok');
    }
}
