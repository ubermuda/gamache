<?php

declare(strict_types=1);

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ConstraintsKeyOutsideAddFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $viewData = [
            'constraints' => 'ui.password_rules',
        ];

        $builder->add('name', TextType::class, [
            'label' => $viewData['constraints'],
        ]);
    }
}
