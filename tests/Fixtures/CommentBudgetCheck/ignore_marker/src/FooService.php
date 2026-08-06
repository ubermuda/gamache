<?php

declare(strict_types=1);

namespace App\Service;

final class FooService
{
    public function documented(): void
    {
        // This block is long on purpose and says so. @comment-budget-ignore
        // Two
        // Three
        // Four
        // Five
        // Six
        // Seven
        $this->run();
    }

    private function run(): void
    {
    }
}
