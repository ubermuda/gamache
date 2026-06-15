<?php

declare(strict_types=1);

namespace Fixture\IsGrantedVoterConstant;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('edit', 'event')]
final class LiteralAttributeController
{
    #[IsGranted(attribute: 'delete', subject: 'event')]
    public function __invoke(): void
    {
    }
}
