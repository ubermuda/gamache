<?php

declare(strict_types=1);

namespace App\Service;

final class FooService
{
    public function twoSmallBlocks(): void
    {
        // One
        // Two
        // Three
        $this->run();

        // Four
        // Five
        // Six
        $this->run();
    }

    private function run(): void
    {
    }
}
