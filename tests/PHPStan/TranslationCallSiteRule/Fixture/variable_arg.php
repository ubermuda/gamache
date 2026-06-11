<?php

declare(strict_types=1);

use Symfony\Contracts\Translation\TranslatorInterface;

function variable_arg_passes(TranslatorInterface $translator, string $key): void
{
    $translator->trans($key);
}
