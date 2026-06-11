<?php

declare(strict_types=1);

use Symfony\Component\Validator\Constraints as Assert;

class ValidEntity
{
    #[Assert\NotBlank(message: 'account.registration.validator.field_required')]
    public string $name = '';

    #[Assert\Length(
        minMessage: 'account.validator.too_short',
        maxMessage: 'account.validator.too_long',
    )]
    public string $username = '';
}
