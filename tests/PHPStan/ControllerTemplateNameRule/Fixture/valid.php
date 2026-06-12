<?php

declare(strict_types=1);

namespace App\Module\Project\Controller;

final class CreateProjectController
{
    public function __invoke(): mixed
    {
        return $this->renderFormResponse('@Project/create_project.html.twig', null);
    }
}

// Renders nothing — exempt even though no template exists for it.
final class DeleteProjectController
{
    public function __invoke(): mixed
    {
        return $this->redirectToRoute('project_list');
    }
}

// Renders only a partial — exempt (turbo-stream / fragment controller).
final class ProjectStreamController
{
    public function __invoke(): mixed
    {
        return $this->render('@Project/_stream.html.twig');
    }
}

namespace App\Module\Account\Controller;

// Template lives in a subdirectory with a different word split:
// registration/check_email.html.twig — normalized matching accepts it.
final class RegistrationCheckEmailController
{
    public function __invoke(): mixed
    {
        return $this->render('@Account/registration/check_email.html.twig');
    }
}

// Directory-grouped flow: security/login.html.twig — the filename alone
// carries the controller name.
final class LoginController
{
    public function __invoke(): mixed
    {
        return $this->render('@Account/security/login.html.twig');
    }
}

namespace App\Controller;

// Outside the configured controller namespace — exempt.
final class HealthCheckController
{
    public function __invoke(): mixed
    {
        return $this->render('health.html.twig');
    }
}
