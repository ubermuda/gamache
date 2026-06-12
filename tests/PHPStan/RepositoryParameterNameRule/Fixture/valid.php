<?php

declare(strict_types=1);

namespace App\Module\Project\Service;

use App\Module\GitHub\Repository\GitHubRepositoryRepository;
use App\Module\Project\Entity\Repository;
use App\Module\Project\Repository\CategoryRepository;
use App\Module\Project\Repository\ProjectRepository;
use App\Module\Project\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityRepository;

final readonly class ValidService
{
    public function __construct(
        private WorkspaceRepository $workspaces,
        private GitHubRepositoryRepository $gitHubRepositories,
        private CategoryRepository $categories,
        private ?ProjectRepository $projects,
        private EntityRepository $repository, // excluded base class
        private Repository $repository2, // class named exactly "Repository" — an entity, not a repository type
        private string $name,
    ) {
    }

    // Non-constructor methods are not subject to the convention.
    public function lookup(WorkspaceRepository $repo): void
    {
    }
}
