<?php

namespace App\Module\GitHub\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ImportGitHubRepoFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('repoUrl', TextType::class, [
            'label' => 'github.form.import_git_hub_repo_form.repo_url',
        ]);
    }
}
