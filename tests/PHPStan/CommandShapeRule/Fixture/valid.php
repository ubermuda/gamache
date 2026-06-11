<?php

declare(strict_types=1);

namespace App\Module\Foo\Command;

final readonly class CreateFooCommand
{
    public function __construct(
        public string $name,
    ) {
    }
}
