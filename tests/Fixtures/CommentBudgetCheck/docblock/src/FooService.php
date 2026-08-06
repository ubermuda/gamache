<?php

declare(strict_types=1);

namespace App\Service;

final class FooService
{
    /**
     * A docblock is not a line-comment run, however long it grows.
     *
     * @param array<string, int> $counts
     * @param list<string>       $names
     * @param non-empty-string   $label
     * @param positive-int       $limit
     *
     * @return array<string, int>
     */
    public function annotated(array $counts, array $names, string $label, int $limit): array
    {
        return $counts;
    }
}
