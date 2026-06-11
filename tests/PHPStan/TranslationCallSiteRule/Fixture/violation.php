<?php

declare(strict_types=1);

use Symfony\Contracts\Translation\TranslatorInterface;

function invalid_trans(TranslatorInterface $translator): void
{
    $translator->trans('Welcome back');
    $translator->trans('Sign in to continue to your account.');
}
