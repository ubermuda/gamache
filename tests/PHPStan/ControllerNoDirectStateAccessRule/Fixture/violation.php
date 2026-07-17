<?php

declare(strict_types=1);

use App\Controller\AppController;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\HttpFoundation\Response;

final class WritingController extends AppController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ObjectRepository $repo,
        private readonly EntityRepository $entities,
    ) {
    }

    public function __invoke(): Response
    {
        $this->entities->findAll();
        $entity = $this->repo->find(1);
        $this->em->persist($entity);
        $this->em->flush();

        return new Response();
    }
}
