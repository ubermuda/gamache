<?php

declare(strict_types=1);

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[ORM\Entity]
class UserEntity
{
    public function __construct(
        public string $email = '',
    ) {
    }
}

/** @extends AbstractType<UserEntity> */
class UserEntityFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserEntity::class,
        ]);
    }
}
