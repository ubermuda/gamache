<?php

declare(strict_types=1);

namespace App\Module\Account\Controller;

use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * A controller whose work the security firewall does. It takes a collaborator
 * and has no handler to wrap, so it is reported unless the project names it in
 * ignoredControllers.
 */
final class LoginController extends AppController
{
    public function __construct(
        private readonly AuthenticationUtils $authenticationUtils,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('account/login.html.twig', [
            'lastUsername' => $this->authenticationUtils->getLastUsername(),
        ]);
    }
}
