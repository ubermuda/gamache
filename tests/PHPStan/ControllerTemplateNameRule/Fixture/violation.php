<?php

declare(strict_types=1);

namespace App\Module\Project\Controller;

// Renders a template, but no template under the module matches the class name.
final class IssueBrainstormController
{
    public function __invoke(): mixed
    {
        return $this->render('@Project/issue_detail.html.twig');
    }
}

// Dynamic template argument still requires a matching template to exist.
final class WorkspaceOverviewController
{
    public function __invoke(string $view): mixed
    {
        return $this->render($view);
    }
}
