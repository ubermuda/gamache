<?php

declare(strict_types=1);

use Symfony\Component\Validator\Constraints as Assert;

class InvalidEntity
{
    #[Assert\NotBlank(message: 'This field should not be blank.')]
    public string $name = '';

    #[Assert\Length(minMessage: 'Too short', maxMessage: 'Too long')]
    public string $username = '';
}
