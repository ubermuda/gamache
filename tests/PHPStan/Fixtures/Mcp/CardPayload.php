<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/** Shapes a response out of what it is given. Not persistence either. */
final readonly class CardPayload
{
    /** @return array{id: string} */
    public function forCard(string $cardId): array
    {
        return ['id' => $cardId];
    }
}
