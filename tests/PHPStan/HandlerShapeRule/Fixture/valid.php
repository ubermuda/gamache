<?php

declare(strict_types=1);

namespace App\Module\Foo\Command;

final readonly class CreateFooHandler
{
    public function __construct(
        private mixed $repo = null,
    ) {
    }

    public function __invoke(): void
    {
    }
}
