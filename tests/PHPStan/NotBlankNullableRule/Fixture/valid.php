<?php

declare(strict_types=1);

use Symfony\Component\Validator\Constraints as Assert;

final class ValidRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $name = null,

        #[Assert\NotBlank]
        public ?string $email = null,
    ) {
    }
}
