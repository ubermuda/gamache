<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('edit', 'event')]
class ValidIsGrantedController extends AppController
{
    public function __invoke(): void
    {
    }
}
