<?php

declare(strict_types=1);

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;

final class DoSomethingHandler
{
    public function __invoke(): void
    {
    }
}

final class DelegatingController extends AppController
{
    public function __construct(
        private readonly DoSomethingHandler $doSomething,
    ) {
    }

    public function __invoke(): Response
    {
        // Inherited helper (receiver is $this) — allowed.
        $this->getUser();

        // Handler invoked as a callable — a FuncCall, not a MethodCall — allowed.
        ($this->doSomething)();

        return $this->render('page.html.twig');
    }
}
