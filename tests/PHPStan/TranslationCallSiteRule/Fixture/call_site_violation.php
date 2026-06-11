<?php

declare(strict_types=1);

use Symfony\Component\Mime\Email;

function configured_call_site_violation(Email $email): void
{
    $email->subject('Password reset confirmation');
}
