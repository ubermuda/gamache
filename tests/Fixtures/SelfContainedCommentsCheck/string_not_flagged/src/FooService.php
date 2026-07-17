<?php

declare(strict_types=1);

namespace Fixtures;

final class FooService
{
    public function eventName(): string
    {
        // record the acknowledgement
        return 'command.unmatched from Task 16 acceptance criteria';
    }
}
