<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/** Scopes a lookup and authorizes it. Not persistence, so calls on it pass. */
final readonly class BoardSubjectResolver
{
    public function requireCard(string $cardId): string
    {
        return $cardId;
    }
}
