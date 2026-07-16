<?php

declare(strict_types=1);

class ValidTemplateReferences
{
    public function show(): string
    {
        return $this->render('@Event/show.html.twig');
    }

    public function dynamic(string $template): string
    {
        return $this->render($template);
    }

    public function notARenderMethod(): string
    {
        return $this->log('Module/Event/show.html.twig');
    }

    public function notTheModuleLayout(): string
    {
        return $this->render('module/event/show.html.twig');
    }

    private function render(string $template): string
    {
        return $template;
    }

    private function log(string $message): string
    {
        return $message;
    }
}
