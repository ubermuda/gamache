<?php

declare(strict_types=1);

// "todo" (lowercase, no @) and "fixme" (lowercase) should not be flagged.
class FooService
{
    public function doSomething(): void
    {
        // This explains the todo list concept — not a real todo marker.
        $steps = ['fixme', 'todo item'];
    }
}
