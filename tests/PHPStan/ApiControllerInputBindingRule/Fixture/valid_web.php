<?php

declare(strict_types=1);

namespace App\Module\Foo\Controller;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// Not in a Controller\Api namespace: forms and raw body reads are fine here,
// so the rule must stay silent.
class FooController
{
    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(FooType::class);
        $raw = $request->getContent();
        $isForm = $form instanceof FormInterface;

        return new Response($raw.($isForm ? '1' : '0'));
    }
}
