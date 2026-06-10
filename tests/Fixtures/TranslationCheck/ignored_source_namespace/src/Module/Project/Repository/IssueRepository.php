<?php

namespace App\Module\Project\Repository;

class IssueRepository
{
    public function findBySlug(): void
    {
        $this->em->createQuery('WHERE i.project = :project AND i.slug = :slug');
    }
}
