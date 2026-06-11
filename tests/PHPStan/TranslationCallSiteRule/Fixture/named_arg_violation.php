<?php

declare(strict_types=1);

use Symfony\Contracts\Translation\TranslatorInterface;

function named_arg_trans(TranslatorInterface $translator): void
{
    $translator->trans(id: 'Welcome back');
    $translator->trans(parameters: [], id: 'Sign in to continue.');
}
