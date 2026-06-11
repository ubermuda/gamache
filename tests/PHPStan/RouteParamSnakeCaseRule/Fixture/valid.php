<?php

declare(strict_types=1);

use Symfony\Component\Routing\Attribute\Route;

#[Route('/{org_id}/projects/{project_slug}', name: 'test_valid')]
class ValidRouteController
{
    public function __invoke(): void
    {
    }
}
