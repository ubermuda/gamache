<?php

declare(strict_types=1);

use Symfony\Contracts\Translation\TranslatorInterface;

function valid_trans(TranslatorInterface $translator): void
{
    $translator->trans('account.login.heading');
    $translator->trans('account.form.registration_form.email.label', [], 'messages');
    $translator->trans('some.key-with_dots.123');
}
