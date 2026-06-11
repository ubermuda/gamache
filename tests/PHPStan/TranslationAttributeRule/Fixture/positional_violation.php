<?php

declare(strict_types=1);

use Symfony\Component\Validator\Constraints as Assert;

class PositionalViolation
{
    // message is the 2nd positional parameter (position 1); null fills the first (options)
    #[Assert\NotBlank(null, 'Please fill this in.')]
    public string $name = '';
}
