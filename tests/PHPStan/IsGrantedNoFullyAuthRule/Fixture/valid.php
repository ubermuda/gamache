<?php

declare(strict_types=1);

use Symfony\Component\Security\Http\Attribute\IsGranted;

// Named arg — but not IS_AUTHENTICATED_FULLY
#[IsGranted(attribute: 'ROLE_USER')]
class ControllerA
{
}

// Voter constant with subject (the correct pattern)
#[IsGranted('some_voter_attribute', subject: 'resource')]
class ControllerB
{
}
