<?php

namespace Gamache\Tests\Rector\Fixture;

use Gamache\Tests\Rector\Entity\SimpleEntity;
use Doctrine\ORM\EntityManagerInterface;

final class SkipNoCustomRepository
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getEntity(int $id): ?SimpleEntity
    {
        return $this->em->getRepository(SimpleEntity::class)->find($id);
    }
}
