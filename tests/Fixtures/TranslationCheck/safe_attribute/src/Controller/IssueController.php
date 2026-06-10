<?php

namespace App\Controller;

use Symfony\Bridge\Doctrine\Attribute\MapEntity;

class IssueController
{
    public function __invoke(
        #[MapEntity(expr: 'repository.findByOrgSlugAndSlug(org, project_slug)')] object $issue,
    ): void {
    }
}
