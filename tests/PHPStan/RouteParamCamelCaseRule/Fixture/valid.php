<?php

declare(strict_types=1);

use Symfony\Component\Routing\Attribute\Route;

#[Route('/{orgId}/projects/{projectSlug}/{id}', name: 'test_valid')]
class ValidRouteController
{
    public function __invoke(): void
    {
    }
}
