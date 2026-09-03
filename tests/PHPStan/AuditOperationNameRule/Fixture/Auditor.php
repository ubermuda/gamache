<?php

declare(strict_types=1);

namespace App\Module\Audit;

final readonly class Auditor
{
    /**
     * @param array<string, scalar|null> $context
     */
    public function record(string $operation, string $outcome, array $context = []): void
    {
    }
}
