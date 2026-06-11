<?php

declare(strict_types=1);

use Doctrine\ORM\Mapping as ORM;

// $id with private(set) is exempt
#[ORM\Entity]
class ValidEntity
{
    public function __construct(
        public private(set) mixed $id = null,
        public string $name = '',
    ) {
    }
}

// Non-entity class — private(set) is allowed anywhere
class NotAnEntity
{
    public function __construct(
        public private(set) string $name = '',
    ) {
    }
}
