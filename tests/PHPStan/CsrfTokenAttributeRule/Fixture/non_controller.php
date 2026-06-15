<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class SomeListener
{
    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager) {}

    public function __invoke(): void
    {
        $this->csrfTokenManager->isTokenValid(new CsrfToken('id', 'token'));
    }
}
