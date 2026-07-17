<?php

declare(strict_types=1);

namespace App\Module\Foo\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

class CreateFooController
{
    public function __invoke(#[MapRequestPayload] FooRequest $payload): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}

// A read endpoint legitimately has no payload — not flagged.
class ListFooController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['items' => []]);
    }
}
