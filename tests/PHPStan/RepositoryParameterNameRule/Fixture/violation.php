<?php

declare(strict_types=1);

namespace App\Module\Project\Service;

use App\Module\Organization\Repository\OrganizationInferenceSettingsRepository;
use App\Module\Project\Repository\ProjectRepository;
use App\Module\Project\Repository\UserRepository;

final readonly class ViolatingService
{
    public function __construct(
        private ProjectRepository $projectRepo,
        private UserRepository $userRepository,
        private OrganizationInferenceSettingsRepository $settingsRepo,
    ) {
    }
}
