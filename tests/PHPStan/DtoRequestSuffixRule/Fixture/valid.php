<?php

declare(strict_types=1);

namespace App\Module\Project\Form;

use Symfony\Component\Form\AbstractType;

// Form type — exempt from the Request-suffix rule
class CreateProjectType extends AbstractType
{
}

// DTO with correct suffix
class CreateProjectRequest
{
}

// Abstract base class — also exempt
abstract class AbstractProjectRequest
{
}
