<?php

declare(strict_types=1);

use Symfony\Component\Validator\Constraints as Assert;

final class BadRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name = '',
    ) {
    }
}
