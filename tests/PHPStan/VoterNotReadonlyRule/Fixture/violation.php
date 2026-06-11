<?php

declare(strict_types=1);

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<'perm', object> */
final readonly class ReadonlyVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        return true;
    }
}
