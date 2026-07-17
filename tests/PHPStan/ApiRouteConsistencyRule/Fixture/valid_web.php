<?php

declare(strict_types=1);

namespace App\Module\Foo\Controller;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/foo', name: 'foo_index')]
class FooController
{
}

// Web route with no name — nothing looks like API, so no mismatch.
#[Route('/foo/create')]
class CreateFooPageController
{
}
