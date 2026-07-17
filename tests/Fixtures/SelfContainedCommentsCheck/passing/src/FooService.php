<?php

declare(strict_types=1);

namespace Fixtures;

// A self-contained comment that states the actual behaviour directly.
final class FooService
{
    public function run(): string
    {
        // Apple's Handoff continuity feature is referenced here. @comment-check-ignore
        return 'ok';
    }
}
