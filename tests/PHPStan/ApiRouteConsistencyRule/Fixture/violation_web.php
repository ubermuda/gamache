<?php

declare(strict_types=1);

namespace App\Module\Foo\Controller;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/foo', name: 'api_foo')]
class MisplacedApiController
{
}

#[Route('/dashboard', name: 'api_dashboard')]
class WebNamedApiController
{
}
