<?php

declare(strict_types=1);

use Symfony\Component\Routing\Attribute\Route;

#[Route('/{orgId}/projects/{projectSlug}', name: 'test_violation')]
class CamelCaseRouteController
{
    public function __invoke(): void
    {
    }
}
