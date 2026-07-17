<?php

declare(strict_types=1);

namespace App\Module\Foo\Controller\Api;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/foo', name: 'api_foo', methods: ['POST'])]
class CreateFooController
{
}

// GET endpoint with no name — path and namespace still agree.
#[Route('/api/foo/list')]
class ListFooController
{
}
