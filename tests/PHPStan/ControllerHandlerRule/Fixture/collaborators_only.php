<?php

declare(strict_types=1);

namespace App\Module\Review\Controller\Collaborating;

use App\Controller\AppController;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class ShowProfileController extends AppController
{
    public function __construct(
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): Response
    {
        $this->logger->info('profile.viewed');

        return $this->render('review/profile.html.twig', [
            'lastUsername' => $this->authenticationUtils->getLastUsername(),
        ]);
    }
}
