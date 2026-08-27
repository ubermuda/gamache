<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/** Returns a list, so a tool wrapping it declares a shape of its own. */
final readonly class TagRepository
{
    /** @return list<array{name: string, documentCount: int}> */
    public function findAll(): array
    {
        return [];
    }
}
