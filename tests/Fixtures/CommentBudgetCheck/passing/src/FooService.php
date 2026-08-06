<?php

declare(strict_types=1);

namespace App\Service;

final class FooService
{
    public function shortNote(): void
    {
        // Partial indexes don't round-trip through DBAL's comparator, so
        // migrate-diff never settles. Keep these plain.
        $this->run();
    }

    public function atBudget(): void
    {
        // One
        // Two
        // Three
        // Four
        // Five
        $this->run();
    }

    private function run(): void
    {
    }
}
