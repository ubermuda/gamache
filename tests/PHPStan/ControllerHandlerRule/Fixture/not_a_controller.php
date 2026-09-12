<?php

declare(strict_types=1);

namespace App\Module\Review\Health;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * A class the project's own base controller does not stand behind. The name
 * says Controller and the parent is Symfony's; ControllerParentRule is the one
 * with something to say about that.
 */
final class HealthCheckController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): Response
    {
        $this->logger->info('health.checked');

        return new Response('ok');
    }
}
