<?php

declare(strict_types=1);

namespace Fixture\IsGrantedVoterConstant;

use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EventVoter
{
    public const string EDIT = 'edit';
}

#[IsGranted(EventVoter::EDIT, 'event')]
#[IsGranted('ROLE_ADMIN')]
#[IsGranted('PUBLIC_ACCESS')]
final class ValidController
{
    public function __invoke(): void
    {
    }
}
