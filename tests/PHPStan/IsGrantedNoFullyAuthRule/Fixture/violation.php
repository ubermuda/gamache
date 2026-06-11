<?php

declare(strict_types=1);

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ViolationControllerA
{
}

#[IsGranted(attribute: 'IS_AUTHENTICATED_FULLY')]
class ViolationControllerB
{
}
