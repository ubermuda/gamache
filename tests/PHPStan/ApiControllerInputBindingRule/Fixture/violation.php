<?php

declare(strict_types=1);

namespace App\Module\Foo\Controller\Api;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class BadApiController
{
    public function __construct(private readonly FormFactoryInterface $forms)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $form = $this->createForm(FooType::class);
        $data = json_decode($request->getContent(), true);

        return new JsonResponse([$form, $data]);
    }
}
