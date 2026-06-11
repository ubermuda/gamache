<?php

declare(strict_types=1);

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreateFooRequest
{
    public function __construct(
        public string $name = '',
    ) {
    }
}

/** @extends AbstractType<CreateFooRequest> */
class CreateFooFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateFooRequest::class,
        ]);
    }
}
