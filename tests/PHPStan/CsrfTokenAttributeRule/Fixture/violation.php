<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ImperativeCsrfController extends AppController
{
    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager) {}

    public function __invoke(): void
    {
        $this->isCsrfTokenValid('id', 'token');
        $this->csrfTokenManager->isTokenValid(new CsrfToken('id', 'token'));
    }
}
