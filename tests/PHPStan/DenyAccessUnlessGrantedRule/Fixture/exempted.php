<?php

declare(strict_types=1);

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;

/**
 * access is enforced per-branch.
 */
class ExemptedController extends AppController
{
    public function __invoke(string $branch): Response
    {
        $this->denyAccessUnlessGranted('branch_access', $branch);

        return new Response('ok');
    }
}
