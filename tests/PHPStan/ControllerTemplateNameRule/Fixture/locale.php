<?php

declare(strict_types=1);

namespace App\Module\Legal\Controller;

class ShowTermsController
{
    public function __invoke(object $request): void
    {
        $this->render('@Legal/show_terms.'.$request->locale.'.html.twig');
    }

    private function render(string $template): void
    {
    }
}

class ShowPrivacyController
{
    public function __invoke(object $request): void
    {
        $this->render('@Legal/show_privacy.'.$request->locale.'.html.twig');
    }

    private function render(string $template): void
    {
    }
}

class ShowCookiesController
{
    public function __invoke(object $request): void
    {
        $this->render('@Legal/show_cookies.english.html.twig');
    }

    private function render(string $template): void
    {
    }
}
