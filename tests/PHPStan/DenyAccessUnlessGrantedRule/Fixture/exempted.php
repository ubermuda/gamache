<?php

declare(strict_types=1);

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * access is enforced per-branch.
 */
class ExemptedController extends AppController
{
    public function __invoke(string $branch): Response
    {
        $this->denyAccessUnlessGranted('branch_access', $branch);

        if ('forbidden' === $branch) {
            throw new AccessDeniedHttpException('nope');
        }

        return new Response('ok');
    }
}
