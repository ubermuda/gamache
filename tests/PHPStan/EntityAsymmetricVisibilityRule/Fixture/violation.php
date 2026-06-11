<?php

declare(strict_types=1);

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ViolatingEntity
{
    public function __construct(
        public private(set) mixed $id = null,
        public private(set) string $name = '',
        public private(set) string $email = '',
    ) {
    }
}
