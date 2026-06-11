<?php

declare(strict_types=1);

namespace Gamache\Tests\Rector\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Entity with no custom repositoryClass — used to test the skip path. */
#[ORM\Entity]
class SimpleEntity
{
}
