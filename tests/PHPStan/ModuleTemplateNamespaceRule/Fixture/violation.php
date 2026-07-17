<?php

declare(strict_types=1);

class ViolatingTemplateReferences
{
    public object $message;

    public function show(): string
    {
        return $this->render('Module/Event/show.html.twig');
    }

    public function invite(): void
    {
        $this->message->htmlTemplate('Module/Notification/email/invite.html.twig');
    }

    private function render(string $template): string
    {
        return $template;
    }
}
