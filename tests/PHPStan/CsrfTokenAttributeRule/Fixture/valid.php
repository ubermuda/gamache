<?php

declare(strict_types=1);

namespace App\Controller;

final class CleanController extends AppController
{
    public function __invoke(): void
    {
        // declarative #[CsrfToken] would go on the class; nothing imperative here
    }
}
