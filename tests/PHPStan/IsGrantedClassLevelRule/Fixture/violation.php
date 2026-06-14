<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;

class MethodLevelIsGrantedController extends AppController
{
    #[IsGranted('edit', 'event')]
    public function __invoke(): void
    {
    }
}
