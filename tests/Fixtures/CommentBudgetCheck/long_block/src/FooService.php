<?php

declare(strict_types=1);

namespace App\Service;

final class FooService
{
    public function narrated(): void
    {
        // One
        // Two
        // Three
        //
        // Five, after a blank line that does not break the run
        // Six
        // Seven
        $this->run();
    }

    private function run(): void
    {
    }
}
