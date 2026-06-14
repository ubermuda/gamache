<?php

declare(strict_types=1);

use Symfony\Component\Routing\Attribute\Route;

#[Route('/{org_id}/projects/{project_slug}', name: 'test_violation')]
class SnakeCaseRouteController
{
    public function __invoke(): void
    {
    }
}
