<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ImperativeCsrfController extends AppController
{
    public function __invoke(CsrfTokenManagerInterface $csrfTokenManager): void
    {
        $this->isCsrfTokenValid('id', 'token');
        $csrfTokenManager->isTokenValid(new CsrfToken('id', 'token'));
    }
}
